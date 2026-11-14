<?php
declare(strict_types=1);
namespace ThesisFlow;

final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
