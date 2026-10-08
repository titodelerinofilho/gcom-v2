<?php

declare(strict_types=1);

namespace App\Service\Health;

use App\Dto\Health\Output\HealthOutput;

final readonly class GetHealthService
{
    public function get(): HealthOutput
    {
        return new HealthOutput('ok');
    }
}
