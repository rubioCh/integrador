<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\Property;
use Illuminate\Database\Seeder;

class AzureSqlProductPropertiesSeeder extends Seeder
{
    public function run(): void
    {
        $azureSqlPlatform = Platform::query()->firstOrCreate(
            ['slug' => 'azure-sql'],
            [
                'name' => 'Azure SQL',
                'type' => 'generic',
                'settings' => [
                    'service_driver' => 'azure_sql',
                ],
                'active' => true,
            ]
        );

        $hubspotPlatform = Platform::query()->firstOrCreate(
            ['slug' => 'hubspot'],
            [
                'name' => 'HubSpot',
                'type' => 'hubspot',
                'active' => true,
            ]
        );

        $definitions = [
            [
                'db_name' => 'Codigo unico del articulo',
                'db_key' => 'itemid',
                'db_type' => 'string',
                'hubspot_name' => 'Identificador DB',
                'hubspot_key' => 'identificador_db',
                'hubspot_type' => 'string',
                'hubspot_url' => 'https://app.hubspot.com/property-settings/50839788/properties?type=0-7&search=nomb&action=edit&property=name',
                'db_label' => 'Codigo unico del articulo',
            ],
            [
                'db_name' => 'Nombre del articulo',
                'db_key' => 'ProductName',
                'db_type' => 'string',
                'hubspot_name' => 'Nombre de la empresa',
                'hubspot_key' => 'name',
                'hubspot_type' => 'string',
                'hubspot_url' => 'https://app.hubspot.com/property-settings/50839788/properties?type=0-7&search=nomb&action=edit&property=name',
                'db_label' => 'Nombre del articulo',
            ],
            [
                'db_name' => 'Nivel 1 jerarquia categoria del articulo',
                'db_key' => 'Categoria',
                'db_type' => 'string',
                'hubspot_name' => 'Categoria',
                'hubspot_key' => 'categoria',
                'hubspot_type' => 'string',
                'hubspot_url' => 'https://app.hubspot.com/property-settings/50839788/properties?type=0-7&action=edit&property=categoria',
                'db_label' => 'Nivel 1 jerarquia categoria del articulo',
            ],
            [
                'db_name' => 'Nivel 2 jerarquia categoria del articulo',
                'db_key' => 'Subcategoria',
                'db_type' => 'string',
                'hubspot_name' => 'Subcatgoria',
                'hubspot_key' => 'subcategoria_db',
                'hubspot_type' => 'string',
                'hubspot_url' => null,
                'db_label' => 'Nivel 2 jerarquia categoria del articulo',
            ],
            [
                'db_name' => 'Nivel 3 jerarquia categoria del articulo',
                'db_key' => 'Subcategoria_1',
                'db_type' => 'string',
                'hubspot_name' => 'Subcatgoria 1',
                'hubspot_key' => 'subcategoria_1_db',
                'hubspot_type' => 'string',
                'hubspot_url' => null,
                'db_label' => 'Nivel 3 jerarquia categoria del articulo',
            ],
            [
                'db_name' => 'Nivel 4 jerarquia categoria del articulo',
                'db_key' => 'Subcategoria_2',
                'db_type' => 'string',
                'hubspot_name' => 'Subcatgoria 2',
                'hubspot_key' => 'subcategoria_2_db',
                'hubspot_type' => 'string',
                'hubspot_url' => null,
                'db_label' => 'Nivel 4 jerarquia categoria del articulo',
            ],
            [
                'db_name' => 'Marca',
                'db_key' => 'Marca',
                'db_type' => 'string',
                'hubspot_name' => 'Marca',
                'hubspot_key' => 'marca_db',
                'hubspot_type' => 'string',
                'hubspot_url' => null,
                'db_label' => 'Marca',
            ],
            [
                'db_name' => 'Precio',
                'db_key' => 'P_Empaque',
                'db_type' => 'float',
                'hubspot_name' => 'Precio unitario',
                'hubspot_key' => 'price',
                'hubspot_type' => 'float',
                'hubspot_url' => null,
                'db_label' => 'Precio',
            ],
            [
                'db_name' => 'Empaque',
                'db_key' => 'taxpackagingqty',
                'db_type' => 'float',
                'hubspot_name' => 'Empaque DB',
                'hubspot_key' => 'empaque_db',
                'hubspot_type' => 'float',
                'hubspot_url' => null,
                'db_label' => 'Empaque',
            ],
            [
                'db_name' => 'Subempaque',
                'db_key' => 'kcp_grcor_taxpackagingqty',
                'db_type' => 'float',
                'hubspot_name' => 'Subempaque',
                'hubspot_key' => 'subempaque_db',
                'hubspot_type' => 'float',
                'hubspot_url' => null,
                'db_label' => 'Subempaque',
            ],
            [
                'db_name' => 'Unidad de medida del articulo',
                'db_key' => 'unitid',
                'db_type' => 'string',
                'hubspot_name' => 'Unidad de medida',
                'hubspot_key' => 'unidad_medida_db',
                'hubspot_type' => 'string',
                'hubspot_url' => null,
                'db_label' => 'Unidad de medida del articulo',
            ],
            [
                'db_name' => 'Cantidad de inventario que tiene el articulo',
                'db_key' => 'Inventario',
                'db_type' => 'float',
                'hubspot_name' => 'Inventario DB',
                'hubspot_key' => 'inventario_db',
                'hubspot_type' => 'float',
                'hubspot_url' => null,
                'db_label' => 'Cantidad de inventario que tiene el articulo',
            ],
        ];

        foreach ($definitions as $definition) {
            Property::query()->updateOrCreate(
                [
                    'platform_id' => $azureSqlPlatform->id,
                    'key' => $definition['db_key'],
                ],
                [
                    'name' => $definition['db_name'],
                    'type' => $definition['db_type'],
                    'required' => false,
                    'active' => true,
                    'meta' => [
                        'table' => 'inventtable',
                        'db_label' => $definition['db_label'],
                        'hubspot_label' => $definition['hubspot_name'],
                        'hubspot_key' => $definition['hubspot_key'],
                        'hubspot_url' => $definition['hubspot_url'],
                        'source_of_truth' => 'Dynamics',
                        'sync_mode' => 'scheduled',
                    ],
                ]
            );

            Property::query()->updateOrCreate(
                [
                    'platform_id' => $hubspotPlatform->id,
                    'key' => $definition['hubspot_key'],
                ],
                [
                    'name' => $definition['hubspot_name'],
                    'type' => $definition['hubspot_type'],
                    'required' => false,
                    'active' => true,
                    'meta' => [
                        'object_type' => 'products',
                        'hubspot_url' => $definition['hubspot_url'],
                        'source_db_table' => 'inventtable',
                        'source_db_key' => $definition['db_key'],
                        'source_db_label' => $definition['db_label'],
                        'source_of_truth' => 'Dynamics',
                        'sync_mode' => 'scheduled',
                    ],
                ]
            );
        }
    }
}
