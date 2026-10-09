<?php

declare(strict_types=1);

namespace App\Integration\Winthor;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\ResellerOracleOutput;

interface ResellerGatewayInterface
{
    public function get(GetResellerProfileInput $input, bool $export = false): ResellerOracleOutput;
}
