<?php

declare(strict_types=1);

namespace App\Service\Reseller;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Dto\Reseller\Output\ResellerPdfOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Audit\AuditEventRepository;
use App\Service\Audit\AuditRecorderService;
use App\Service\Enterprise\GetEnterpriseService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

final readonly class ExportResellerProfileService
{
    public function __construct(private GetResellerProfileService $profiles, private ResellerPdfTemplateService $template, private GetEnterpriseService $enterprise, private AuditRecorderService $audit, private AuditEventRepository $auditEvents)
    {
    }

    public function export(GetResellerProfileInput $input, User $actor): ResellerPdfOutput
    {
        $profile = $this->profiles->get($input, true);
        $path = tempnam(sys_get_temp_dir(), 'gcom-reseller-');

        if (false === $path) {
            throw new BusinessException('Não foi possível preparar o PDF.', 503);
        }

        try {
            $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'tempDir' => sys_get_temp_dir(), 'fontCache' => sys_get_temp_dir()]));
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml($this->template->render($profile, $this->enterprise->get()->tradeName), 'UTF-8');
            $pdf->render();
            $pdf->getCanvas()->page_text(30, 816, 'GCOM · Ficha do Cliente Revenda · {PAGE_NUM} / {PAGE_COUNT}', null, 7);

            if (false === file_put_contents($path, $pdf->output())) {
                throw new BusinessException('Não foi possível salvar o PDF.', 503);
            }

            $this->audit->record($actor, 'reseller_profile.exported', $profile->customer->code, [
                'from' => $profile->from, 'to' => $profile->to, 'queriedAt' => $profile->generatedAt,
                'ratingVersion' => $profile->rating->version, 'ratingScore' => $profile->rating->score,
                'fingerprint' => hash('sha256', json_encode($profile, \JSON_THROW_ON_ERROR)),
            ]);

            $this->auditEvents->savePending();

            return new ResellerPdfOutput($path, 'ficha-revenda-'.$profile->customer->code.'-'.$profile->from.'-'.$profile->to.'.pdf');
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }
    }
}
