<?php

namespace App\WebhookClient;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\Exceptions\InvalidConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class WebhookCustomSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header($config->signatureHeaderName)
            ?? $request->query($config->signatureHeaderName);

        if (! $signature) {
            return false;
        }

        $signingSecret = $config->signingSecret;
        if (empty($signingSecret)) {
            throw InvalidConfig::signingSecretNotSet();
        }

        if (! $request->query($config->signatureHeaderName)) {
            $computedSignature = hash('sha256', $signingSecret . $request->getContent());
            return hash_equals($signature, $computedSignature);
        }

        return hash_equals($signature, $signingSecret);
    }
}
