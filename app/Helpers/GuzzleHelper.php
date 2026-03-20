<?php

namespace App\Helpers;

use App\Services\CustomBodySummarizer;
use GuzzleHttp\Exception\RequestException;

class GuzzleHelper
{
    public static function getCompleteErrorMessage(RequestException $e): string
    {
        $message = $e->getMessage();

        if (strpos($message, '(truncated...)') !== false) {
            $customSummarizer = new CustomBodySummarizer();

            $newException = RequestException::create(
                $e->getRequest(),
                $e->getResponse(),
                $e->getPrevious(),
                $e->getHandlerContext(),
                $customSummarizer
            );

            return $newException->getMessage();
        }

        return $message;
    }

    public static function getCompleteResponseBody(RequestException $e): ?string
    {
        if (! $e->hasResponse()) {
            return null;
        }

        $response = $e->getResponse();
        $body = $response->getBody();

        if (! $body->isSeekable() || ! $body->isReadable()) {
            return null;
        }

        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        return $content;
    }

    public static function getCompleteHubSpotErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        if (self::isHubSpotException($e)) {
            if (method_exists($e, 'getResponseBody')) {
                $responseBody = $e->getResponseBody();
                if ($responseBody) {
                    return $message . "\nResponse Body: " . $responseBody;
                }
            }

            if (method_exists($e, 'getResponseHeaders')) {
                $headers = $e->getResponseHeaders();
                if ($headers) {
                    return $message . "\nResponse Headers: " . json_encode($headers);
                }
            }
        }

        return $message;
    }

    public static function getCompleteErrorMessageFromAnyException(\Exception $e): string
    {
        if ($e instanceof RequestException) {
            return self::getCompleteErrorMessage($e);
        }

        return self::getCompleteHubSpotErrorMessage($e);
    }

    private static function isHubSpotException(\Exception $e): bool
    {
        $classes = [
            'HubSpot\\Client\\Crm\\Objects\\ApiException',
            'HubSpot\\Client\\Crm\\Notes\\ApiException',
            'HubSpot\\Client\\Crm\\Quotes\\ApiException',
            'HubSpot\\Client\\Crm\\Companies\\ApiException',
            'HubSpot\\Client\\Crm\\Contacts\\ApiException',
            'HubSpot\\Client\\Crm\\Products\\ApiException',
            'HubSpot\\Client\\Crm\\Associations\\ApiException',
            'HubSpot\\Client\\Files\\ApiException',
        ];

        foreach ($classes as $class) {
            if (class_exists($class) && $e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
