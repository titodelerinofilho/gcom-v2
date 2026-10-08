<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Dto\Audit\Output\InvoiceAuditStateOutput;
use App\Dto\Audit\Output\OrderAuditChangeOutput;
use App\Dto\Audit\Output\OrderAuditOutput;
use App\Entity\Order\OrderSnapshot;
use Brick\Math\BigDecimal;

final class CompareOrderSnapshotService
{
    public function compare(OrderSnapshot $snapshot, array $current): OrderAuditOutput
    {
        $header = $snapshot->getHeader();
        $changes = [];
        $invoiceBaseline = true === array_key_exists('COMMISSION_INVOICES', $header);

        if (null === $current['header']) {
            $changes[] = new OrderAuditChangeOutput('Pedido', $snapshot->getOrderNumber(), 'Existência no Winthor', 'Presente', 'Não encontrado');
        } else {
            $this->fields('Pedido', $snapshot->getOrderNumber(), $header, $current['header'], $changes);
        }
        $this->records('Produto', array_map(static fn ($item): array => $item->getRaw(), $snapshot->getItems()->toArray()), $current['items'], 'NUMSEQ', 'CODPROD', $changes);

        if (true === $invoiceBaseline) {
            $this->records('Nota fiscal', $header['COMMISSION_INVOICES'], $current['invoices'], 'NUMTRANSVENDA', 'NUMNOTA', $changes);
        }

        $invoices = array_map(static fn (array $invoice): InvoiceAuditStateOutput => new InvoiceAuditStateOutput((string) $invoice['NUMNOTA'], null === ($invoice['DTCANCEL'] ?? null) ? null : (string) $invoice['DTCANCEL']), $current['invoices']);

        return new OrderAuditOutput($snapshot->getOrderNumber(), (string) ($header['NUMNOTA'] ?? $header['NUMCUPOM'] ?? '—'), $snapshot->getCapturedAt()->format(\DATE_ATOM), null === $current['header'] ? 'missing' : ([] === $changes ? (true === $invoiceBaseline ? 'unchanged' : 'incomplete') : 'changed'), $invoiceBaseline, $changes, $invoices);
    }

    private function fields(string $section, string $record, array $saved, array $current, array &$changes): void
    {
        foreach ($saved as $field => $value) {
            if (true === str_starts_with($field, 'COMMISSION_') || 'DATA_ISO' === $field) {
                continue;
            }
            $before = null === $value ? null : (string) $value;
            $after = null === ($current[$field] ?? null) ? null : (string) $current[$field];
            $same = $before === $after;

            if (null !== $before && null !== $after && true === is_numeric($before) && true === is_numeric($after)) {
                $same = BigDecimal::of($before)->isEqualTo($after);
            }

            if (false === $same) {
                $changes[] = new OrderAuditChangeOutput($section, $record, $field, $before, $after);
            }
        }
    }

    private function records(string $section, array $saved, array $current, string $identity, string $fallback, array &$changes): void
    {
        $index = static function (array $rows) use ($identity, $fallback): array {
            $result = [];
            foreach ($rows as $row) {
                $key = (string) ($row[$identity] ?? $row[$fallback]);
                $occurrence = 0;
                while (true === isset($result[$key.':'.$occurrence])) {
                    ++$occurrence;
                }
                $result[$key.':'.$occurrence] = $row;
            }

            return $result;
        };
        $saved = $index($saved);
        $current = $index($current);
        foreach ($saved as $key => $row) {
            $record = (string) ($row[$fallback] ?? $key);

            if (false === isset($current[$key])) {
                $changes[] = new OrderAuditChangeOutput($section, $record, 'Registro', 'Presente', 'Removido');

                continue;
            }
            $this->fields($section, $record, $row, $current[$key], $changes);
        }
        foreach ($current as $key => $row) {
            if (false === isset($saved[$key])) {
                $changes[] = new OrderAuditChangeOutput($section, (string) ($row[$fallback] ?? $key), 'Registro', null, 'Adicionado');
            }
        }
    }
}
