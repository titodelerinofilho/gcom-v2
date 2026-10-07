<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReportRepository;
use App\Service\AuditRecorder;
use App\Service\Input;
use App\Service\ReportExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class ReportController extends AbstractController
{
    private function range(Request $r): array
    {
        $from = Input::date(['from' => $r->query->get('from', date('Y-01-01'))], 'from');
        $to = Input::date(['to' => $r->query->get('to', date('Y-m-d'))], 'to');

        if ($to < $from || $from->diff($to)->days > 3660) {
            throw new \App\Exception\BusinessException('Período inválido (máximo 10 anos).');
        }

        return [$from->format('Y-m-d'), $to->modify('+1 day')->format('Y-m-d')];
    }

    #[Route('/api/reports/summary', methods: ['GET'])]
    public function summary(Request $r, ReportRepository $repo): JsonResponse
    {
        return $this->json($repo->summary(...$this->range($r)));
    }

    private function filters(Request $request, string $kind): array
    {
        $filters = [];
        foreach (['customer', 'orderNumber'] as $key) {
            $value = $request->query->get($key, '');

            if ('' !== $value) {
                if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D', $value)) {
                    throw new \App\Exception\BusinessException('Filtro inválido: '.$key.'.');
                }
                $filters[$key] = $value;
            }
        }
        $choices = ['status' => ['pending', 'approved', 'paid'], 'mode' => ['normal', 'atg'],
            'type' => ['debt', 'return', 'cancellation'], 'state' => ['pending', 'deducted'],
            'dateBasis' => 'commissions' === $kind ? ['created', 'paid'] : ['created', 'applied']];
        foreach ($choices as $key => $allowed) {
            $value = $request->query->get($key, '');

            if ('' !== $value) {
                if (!in_array($value, $allowed, true)) {
                    throw new \App\Exception\BusinessException('Filtro inválido: '.$key.'.');
                }
                $filters[$key] = $value;
            }
        }

        if (('commissions' === $kind && (isset($filters['state']) || isset($filters['type']))) || ('adjustments' === $kind && isset($filters['orderNumber']))) {
            throw new \App\Exception\BusinessException('Filtro incompatível com o relatório escolhido.');
        }

        return $filters;
    }

    #[Route('/api/reports/{kind}', requirements: ['kind' => 'commissions|adjustments'], methods: ['GET'])]
    public function search(string $kind, Request $request, ReportRepository $repo): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return $this->json($repo->search($from, $to, $this->filters($request, $kind), $kind, min(100000, max(1, $request->query->getInt('page', 1)))));
    }

    #[Route('/api/reports/{kind}.{format}', requirements: ['kind' => 'commissions|adjustments', 'format' => 'csv|xlsx|pdf'], methods: ['GET'])]
    public function export(string $kind, string $format, Request $r, ReportExporter $exporter, AuditRecorder $audit, EntityManagerInterface $em): BinaryFileResponse
    {
        [$from, $to] = $this->range($r);
        $filters = $this->filters($r, $kind);
        $path = $exporter->generate($format, $from, $to, $filters, $kind);

        try {
            $audit->record($this->getUser(), 'report.exported', $kind, ['format' => $format, 'from' => $from, 'toExclusive' => $to, 'filters' => $filters]);
            $em->flush();
        } catch (Throwable $e) {
            unlink($path);

            throw $e;
        }
        $type = ['csv' => 'text/csv; charset=utf-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];
        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $type, 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'gcom-'.$kind.'.'.$format);
        $response->deleteFileAfterSend();

        return $response;
    }
}
