<?php

namespace App\Services\AzureSql;

use App\Models\Event;
use App\Models\Platform;
use App\Models\PropertyRelationship;
use App\Models\Record;
use App\Services\Base\BaseService;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AzureSqlService extends BaseService
{
    private const RUNTIME_CONNECTION = 'azure_sql_runtime';

    /**
     * @var array<string, list<string>>
     */
    private const UPSERTABLE_COLUMNS = [
        'inventtable' => [
            'itemid',
            'ProductName',
            'Categoria',
            'Subcategoria',
            'Subcategoria_1',
            'Subcategoria_2',
            'Marca',
            'P_Empaque',
            'taxpackagingqty',
            'kcp_grcor_taxpackagingqty',
            'unitid',
            'Inventario',
        ],
        'custtable' => [
            'accountnum',
            'custname',
            'Segmen ID',
            'Sub-Segmen ID',
            'Telefono',
            'Correo',
            'Equipo_Ventas',
            'Sales District ID',
            'Empleado_responsable',
            'address',
            'RNC',
        ],
        'contactos_cl' => [
            'accountnum',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const NUMERIC_COLUMNS = [
        'inventtable' => [
            'P_Empaque',
            'taxpackagingqty',
            'kcp_grcor_taxpackagingqty',
            'Inventario',
        ],
        'custtable' => [],
        'contactos_cl' => [],
    ];

    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        protected ?HubspotApiServiceRefactored $hubspotApi = null
    ) {
        parent::__construct($platform, $event, $record);

        $this->hubspotApi ??= app(HubspotApiServiceRefactored::class);
    }

    public function testConnection(): array
    {
        try {
            $connection = $this->makeConnection();
            $result = $connection->select('SELECT 1 as ok');

            return [
                'success' => true,
                'message' => 'Azure SQL connection validated.',
                'data' => [
                    'driver' => self::RUNTIME_CONNECTION,
                    'rows_returned' => count($result),
                    'config' => $this->sanitizedConnectionDetails(),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Azure SQL connection failed.',
                'data' => [
                    'config' => $this->sanitizedConnectionDetails(),
                ],
                'error' => [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    public function syncProducts(array $payload = []): array
    {
        return $this->syncTable(
            table: 'inventtable',
            hubspotObjectType: 'products',
            defaultQuery: 'SELECT * FROM [dbo].[inventtable]',
            payload: $payload,
            criteriaResolver: function (array $row): array {
                $identifier = $this->stringValue($row['itemid'] ?? null);

                return [
                    ['property' => 'identificador_db', 'value' => $identifier],
                    ['property' => 'sku', 'value' => $identifier],
                ];
            }
        );
    }

    public function syncAccounts(array $payload = []): array
    {
        return $this->syncTable(
            table: 'custtable',
            hubspotObjectType: 'companies',
            defaultQuery: 'SELECT * FROM [dbo].[custtable]',
            payload: $payload,
            criteriaResolver: function (array $row): array {
                return [
                    ['property' => 'identificador_db', 'value' => $this->stringValue($row['accountnum'] ?? null)],
                    ['property' => 'email', 'value' => $this->stringValue($row['Correo'] ?? null)],
                    ['property' => 'phone', 'value' => $this->stringValue($row['Telefono'] ?? null)],
                ];
            }
        );
    }

    public function syncContacts(array $payload = []): array
    {
        return $this->syncTable(
            table: 'contactos_cl',
            hubspotObjectType: 'contacts',
            defaultQuery: 'SELECT * FROM [dbo].[contactos_cl]',
            payload: $payload,
            criteriaResolver: function (array $row): array {
                $locator = $this->stringValue($row['locator'] ?? null);
                $type = Str::lower($this->stringValue($row['Tipo'] ?? null) ?? '');

                $email = in_array($type, ['correo', 'email', 'mail'], true) ? $locator : null;
                $phone = in_array($type, ['telefono', 'teléfono', 'phone', 'celular'], true) ? $locator : null;

                return [
                    ['property' => 'identificador_db', 'value' => $this->stringValue($row['accountnum'] ?? null)],
                    ['property' => 'email', 'value' => $email],
                    ['property' => 'phone', 'value' => $phone],
                ];
            }
        );
    }

    private function syncTable(string $table, string $hubspotObjectType, string $defaultQuery, array $payload, callable $criteriaResolver): array
    {
        $query = trim((string) ($payload['command_sql'] ?? $this->event?->command_sql ?? $defaultQuery));
        $rowsRead = 0;
        $matchedRows = 0;
        $updatedRows = 0;
        $warningRows = 0;
        $updatedIds = [];
        $warnings = [];

        try {
            $rows = $this->fetchRows($query, $table);
            $rowsRead = count($rows);
        } catch (\Throwable $exception) {
            return $this->failureResult(
                'Azure SQL query failed.',
                $table,
                $query,
                $exception
            );
        }

        foreach ($rows as $index => $row) {
            $match = $this->findHubspotMatch($hubspotObjectType, $criteriaResolver($row));

            if (! ($match['success'] ?? false)) {
                $warningRows++;
                $warnings[] = [
                    'row' => $index,
                    'reason' => $match['message'] ?? 'match_not_found',
                    'criteria' => $match['criteria_attempted'] ?? [],
                ];
                continue;
            }

            $matchedRows++;

            $properties = $this->buildHubspotPayload($table, $row);
            if (empty($properties)) {
                $warningRows++;
                $warnings[] = [
                    'row' => $index,
                    'reason' => 'no_updatable_properties',
                    'hubspot_id' => $match['hubspot_id'] ?? null,
                ];
                continue;
            }

            $response = $this->hubspotApi->updateObject(
                $hubspotObjectType,
                (string) $match['hubspot_id'],
                $properties
            );

            if (! ($response['success'] ?? false)) {
                $warningRows++;
                $warnings[] = [
                    'row' => $index,
                    'reason' => 'hubspot_update_failed',
                    'hubspot_id' => $match['hubspot_id'] ?? null,
                    'criteria_used' => $match['matched_by'] ?? null,
                    'error' => $response['error'] ?? null,
                ];
                continue;
            }

            $updatedRows++;
            $updatedIds[] = (string) $match['hubspot_id'];
        }

        $details = [
            'table' => $table,
            'query' => $query,
            'rows_read' => $rowsRead,
            'rows_matched' => $matchedRows,
            'rows_updated' => $updatedRows,
            'rows_warning' => $warningRows,
            'hubspot_updated_ids' => $updatedIds,
            'warnings' => $warnings,
            'connection' => $this->sanitizedConnectionDetails(),
        ];

        $this->mergeRecordDetails($details);

        if ($rowsRead === 0) {
            return [
                'success' => true,
                'status' => 'warning',
                'message' => sprintf('Azure SQL sync finished for %s with no rows returned.', $table),
                'data' => $details,
            ];
        }

        if ($updatedRows === 0) {
            return [
                'success' => true,
                'status' => 'warning',
                'message' => sprintf('Azure SQL sync finished for %s without HubSpot updates.', $table),
                'data' => $details,
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Azure SQL sync finished for %s.', $table),
            'data' => $details,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $query, string $table): array
    {
        $connection = $this->makeConnection();
        $rows = $connection->select($query);

        return array_map(function (object|array $row) use ($table): array {
            $values = (array) $row;
            $normalized = [];

            foreach ($values as $key => $value) {
                $normalized[$key] = $this->normalizeValue($table, (string) $key, $value);
            }

            return $normalized;
        }, $rows);
    }

    private function makeConnection(): ConnectionInterface
    {
        config([
            'database.connections.' . self::RUNTIME_CONNECTION => $this->buildConnectionConfig(),
        ]);

        DB::purge(self::RUNTIME_CONNECTION);

        return DB::connection(self::RUNTIME_CONNECTION);
    }

    private function buildConnectionConfig(): array
    {
        $credentials = is_array($this->platform->credentials) ? $this->platform->credentials : [];
        $settings = is_array($this->platform->settings) ? $this->platform->settings : [];

        return [
            'driver' => 'sqlsrv',
            'host' => $settings['host'] ?? $credentials['host'] ?? 'localhost',
            'port' => (string) ($settings['port'] ?? $credentials['port'] ?? '1433'),
            'database' => $settings['database'] ?? $credentials['database'] ?? '',
            'username' => $credentials['username'] ?? '',
            'password' => $credentials['password'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => (bool) ($settings['encrypt'] ?? true),
            'trust_server_certificate' => (bool) ($settings['trust_server_certificate'] ?? false),
            'options' => array_filter([
                'LoginTimeout' => (int) ($settings['login_timeout'] ?? 30),
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @param  list<array{property:string, value:mixed}>  $criteria
     * @return array<string, mixed>
     */
    private function findHubspotMatch(string $objectType, array $criteria): array
    {
        $attempted = [];

        foreach ($criteria as $criterion) {
            $property = (string) ($criterion['property'] ?? '');
            $value = $criterion['value'] ?? null;

            if ($property === '' || $value === null || $value === '') {
                continue;
            }

            $attempted[] = [
                'property' => $property,
                'value' => is_scalar($value) ? (string) $value : '[non_scalar]',
            ];

            $response = $this->hubspotApi->searchObjectByProperty($objectType, $property, $value, [$property]);

            if (! ($response['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'hubspot_search_failed',
                    'criteria_attempted' => $attempted,
                    'error' => $response['error'] ?? null,
                ];
            }

            $results = Arr::get($response, 'data.results', []);
            if (! is_array($results) || count($results) === 0) {
                continue;
            }

            if (count($results) > 1) {
                return [
                    'success' => false,
                    'message' => 'multiple_hubspot_matches',
                    'criteria_attempted' => $attempted,
                ];
            }

            return [
                'success' => true,
                'hubspot_id' => (string) Arr::get($results, '0.id'),
                'matched_by' => $property,
                'criteria_attempted' => $attempted,
            ];
        }

        return [
            'success' => false,
            'message' => 'hubspot_match_not_found',
            'criteria_attempted' => $attempted,
        ];
    }

    private function buildHubspotPayload(string $table, array $row): array
    {
        $allowedColumns = self::UPSERTABLE_COLUMNS[$table] ?? [];
        $payload = [];

        if ($this->event) {
            $this->event->loadMissing([
                'propertyRelationships.property:id,key,name',
                'propertyRelationships.relatedProperty:id,key,name',
            ]);
        }

        $relationships = $this->event?->propertyRelationships ?? collect();
        foreach ($relationships as $relationship) {
            if (! $relationship instanceof PropertyRelationship || ! $relationship->active) {
                continue;
            }

            $sourceKey = $relationship->mapping_key ?: $relationship->property?->key;
            $targetKey = $relationship->relatedProperty?->key;

            if (! is_string($sourceKey) || trim($sourceKey) === '' || ! is_string($targetKey) || trim($targetKey) === '') {
                continue;
            }

            if (! in_array($sourceKey, $allowedColumns, true)) {
                continue;
            }

            $value = $row[$sourceKey] ?? null;
            if ($value === null) {
                continue;
            }

            $payload[$targetKey] = $value;
        }

        if (! empty($payload)) {
            return $payload;
        }

        $fallbackMapping = is_array($this->event?->payload_mapping) ? $this->event?->payload_mapping : [];
        foreach ($fallbackMapping as $sourceKey => $targetKey) {
            if (! is_string($sourceKey) || ! is_string($targetKey)) {
                continue;
            }

            if (! in_array($sourceKey, $allowedColumns, true)) {
                continue;
            }

            $value = $row[$sourceKey] ?? null;
            if ($value === null) {
                continue;
            }

            $payload[$targetKey] = $value;
        }

        return $payload;
    }

    private function normalizeValue(string $table, string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (in_array($column, self::NUMERIC_COLUMNS[$table] ?? [], true)) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function failureResult(string $message, string $table, string $query, \Throwable $exception): array
    {
        $details = [
            'table' => $table,
            'query' => $query,
            'connection' => $this->sanitizedConnectionDetails(),
            'error' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ],
        ];

        $this->mergeRecordDetails($details);

        return [
            'success' => false,
            'message' => $message,
            'data' => $details,
        ];
    }

    private function sanitizedConnectionDetails(): array
    {
        $settings = is_array($this->platform->settings) ? $this->platform->settings : [];
        $credentials = is_array($this->platform->credentials) ? $this->platform->credentials : [];

        return array_filter([
            'driver' => $settings['service_driver'] ?? $credentials['service_driver'] ?? null,
            'host' => $settings['host'] ?? $credentials['host'] ?? null,
            'port' => $settings['port'] ?? $credentials['port'] ?? null,
            'database' => $settings['database'] ?? $credentials['database'] ?? null,
            'username' => $credentials['username'] ?? null,
            'encrypt' => $settings['encrypt'] ?? null,
            'trust_server_certificate' => $settings['trust_server_certificate'] ?? null,
            'login_timeout' => $settings['login_timeout'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function mergeRecordDetails(array $details): void
    {
        if (! $this->record) {
            return;
        }

        $existing = is_array($this->record->details) ? $this->record->details : [];

        $this->record->update([
            'details' => array_replace_recursive($existing, $details),
        ]);
    }
}
