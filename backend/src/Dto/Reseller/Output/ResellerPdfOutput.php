<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ResellerPdfOutput
{
    public function __construct(public string $path, public string $filename)
    {
    }
}
