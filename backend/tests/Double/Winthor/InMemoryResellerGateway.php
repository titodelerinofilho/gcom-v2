<?php

declare(strict_types=1);

namespace App\Tests\Double\Winthor;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\ActivityOutput;
use App\Dto\Reseller\Output\CancellationOutput;
use App\Dto\Reseller\Output\CancellationsOutput;
use App\Dto\Reseller\Output\CustomerOutput;
use App\Dto\Reseller\Output\DebtOutput;
use App\Dto\Reseller\Output\DebtsOutput;
use App\Dto\Reseller\Output\DebtSummaryOutput;
use App\Dto\Reseller\Output\ResellerOracleOutput;
use App\Dto\Reseller\Output\SalesMonthOutput;
use App\Dto\Reseller\Output\TopCustomerOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\ResellerGatewayInterface;

final class InMemoryResellerGateway implements ResellerGatewayInterface
{
    public static bool $unavailable = false;

    public static int $debtTotal = 2;

    public function get(GetResellerProfileInput $input, bool $export = false): ResellerOracleOutput
    {
        if (true === self::$unavailable) {
            throw new BusinessException('Winthor indisponível.', 503);
        }

        if ('100' !== $input->customer) {
            throw new BusinessException('Cliente não encontrado.', 404);
        }

        return new ResellerOracleOutput(
            new CustomerOutput('100', 'Revenda <Teste> & Companhia'),
            new ActivityOutput(65, '130000.00', 5, 3, 2, '2000.00', '1000.00'),
            new DebtSummaryOutput(self::$debtTotal, '500.00', 1, '120.00', '0.00', '120.00', 8),
            new DebtsOutput([new DebtOutput('200', 'Vinculado', false, '10', '1', '1001', '2026-10-01', 8, '120.00'), new DebtOutput('100', 'Revenda', true, '11', '1', null, '2026-10-31', 0, '380.00')], self::$debtTotal, true === $export ? 1 : $input->debtsPage, true === $export ? 1000 : 10),
            new CancellationsOutput([new CancellationOutput('125', '200', 'Vinculado', '2026-10-04', '1000.00', 'Pedido cancelado', 'order'), new CancellationOutput('126', '200', 'Vinculado', '2026-10-05', '1000.00', 'Cancelamento fiscal', 'invoice')], 2, true === $export ? 1 : $input->cancellationsPage, true === $export ? 1000 : 5),
            [new SalesMonthOutput(substr($input->from, 0, 7), 65, '130000.00')],
            [new TopCustomerOutput('200', 'Vinculado', 65, '130000.00')],
        );
    }
}
