<?php

declare(strict_types=1);

namespace App\Service\Report;

use DateTimeImmutable;
use DateTimeZone;

final class ReportFilenameService
{
    public function create(string $kind, string $format): string
    {
        $name = 'commissions' === $kind ? 'comissoes' : 'deducoes';
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza')))->format('Y-m-d_H-i-s');

        return 'gcom-'.$name.'-'.$timestamp.'.'.$format;
    }
}
