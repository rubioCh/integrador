<?php

namespace App\Services;

use GuzzleHttp\BodySummarizerInterface;
use Psr\Http\Message\MessageInterface;

class CustomBodySummarizer implements BodySummarizerInterface
{
    public function summarize(MessageInterface $message): ?string
    {
        $body = $message->getBody();

        if (! $body->isSeekable() || ! $body->isReadable()) {
            return null;
        }

        $size = $body->getSize();
        if ($size === 0) {
            return null;
        }

        $body->rewind();
        $summary = $body->getContents();
        $body->rewind();

        if (preg_match('/[^\pL\pM\pN\pP\pS\pZ\n\r\t]/u', $summary) !== 0) {
            return null;
        }

        return $summary;
    }
}
