<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use thiagoalessio\TesseractOCR\TesseractOCR;

class KtpOcrService
{
    /**
     * OUTPUT:
     * [
     *   nama_pewaris,
     *   nik,
     *   rw,
     *   nomor_surat
     * ]
     */
    public function extract(string $path): array
    {
        try {
            if (!file_exists($path)) {
                Log::error('OCR file tidak ditemukan', ['path' => $path]);
                return $this->emptyResult();
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            // ======================
            // IMAGE
            // ======================
            if ($ext !== 'pdf') {
                return $this->extractFromImage($path);
            }

            // ======================
            // PAGE 1 → NAMA + NIK
            // ======================
            $page1 = ['nama_pewaris' => null, 'nik' => null];
            $img1 = $this->convertPdfToPngUsingPoppler($path, 1, 450);

            if ($img1) {
                $page1 = $this->extractPage1NamaNik($img1);
            }

            // ======================
            // PAGE 3 → RW
            // ======================
            $rw = null;
            $img3 = $this->convertPdfToPngUsingPoppler($path, 3, 450);

            if ($img3) {
                $raw3 = $this->tesseractManualToString($img3, 11, 'ind');
                Log::info('OCR PAGE 3 RAW', ['raw' => $raw3]);

                $rw = $this->extractRw(
                    $this->normalizeText($raw3)
                );
            }

            // ======================
            // PAGE 3–7 → NOMOR SURAT
            // ======================
            $nomorSurat = $this->extractNomorSuratFromPages($path, 3, 7);

            Log::info('OCR FINAL RESULT', [
                'nama' => $page1['nama_pewaris'],
                'nik' => $page1['nik'],
                'rw' => $rw,
                'nomor_surat' => $nomorSurat,
            ]);

            return [
                'raw' => null,
                'nama_pewaris' => $page1['nama_pewaris'],
                'nik' => $page1['nik'],
                'rw' => $rw,
                'nomor_surat' => $nomorSurat,
            ];

        } catch (\Throwable $e) {
            Log::error('OCR GLOBAL ERROR', ['msg' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    // =====================================================
    // PAGE 1 → NAMA + NIK
    // =====================================================
    private function extractPage1NamaNik(string $imagePath): array
    {
        $raw = (new TesseractOCR($imagePath))
            ->lang('ind')
            ->psm(11)
            ->run();

        Log::info('OCR PAGE 1 RAW', ['raw' => $raw]);

        $text = $this->normalizeText($raw);

        return [
            'nama_pewaris' =>
                $this->extractNamaPewaris($text)
                ?: $this->extractNama($text),
            'nik' => $this->cleanNik(
                $this->extractNik($text)
            ),
        ];
    }

    // =====================================================
    // PAGE 3–7 → NOMOR SURAT
    // =====================================================
    private function extractNomorSuratFromPages(
        string $pdfPath,
        int $start,
        int $end
    ): ?string {

        for ($page = $start; $page <= $end; $page++) {

            $img = $this->convertPdfToPngUsingPoppler($pdfPath, $page, 400);
            if (!$img || !file_exists($img)) continue;

            $raw = $this->tesseractManualToString($img, 6, 'ind');
            if (!$raw) continue;

            $normalized = $this->normalizeText($raw);

            $targetText = $this->getBottomRightSection($normalized);
            $nomor = $this->extractNomorSurat($targetText);

            Log::info('CHECK NOMOR SURAT', [
                'page' => $page,
                'found' => $nomor,
            ]);

            if ($nomor) return $nomor;
        }

        return null;
    }

    // =====================================================
    // FILTER TEKS KANAN-BAWAH
    // =====================================================
    private function getBottomRightSection(string $text): string
    {
        $lines = array_values(
            array_filter(array_map('trim', explode("\n", $text)))
        );

        $total = count($lines);
        if ($total < 10) return $text;

        $start = (int) floor($total * 0.65);
        $bottomLines = array_slice($lines, $start);

        $right = [];

        foreach ($bottomLines as $line) {
            if (
                preg_match('/NOMOR\s*[:\-]/i', $line) ||
                preg_match('/\b\d{2}(?:\/\d{2}){4}\b/', $line)
            ) {
                $right[] = $line;
            }
        }

        return implode("\n", $right);
    }

    // =====================================================
    // REGEX NOMOR SURAT
    // =====================================================
    private function extractNomorSurat(string $text): ?string
    {
        if (preg_match_all('/(\d{2}(?:\/\d{2}){4})/', $text, $m)) {
            return end($m[1]);
        }
        return null;
    }

    // =====================================================
    // UTILITIES
    // =====================================================
    private function emptyResult(): array
    {
        return [
            'raw' => null,
            'nama_pewaris' => null,
            'nik' => null,
            'rw' => null,
            'nomor_surat' => null,
        ];
    }

    private function extractFromImage(string $image): array
    {
        $raw = (new TesseractOCR($image))
            ->lang('ind')
            ->psm(11)
            ->run();

        $text = $this->normalizeText($raw);

        return [
            'raw' => $raw,
            'nama_pewaris' => $this->extractNama($text),
            'nik' => $this->extractNik($text),
            'rw' => $this->extractRw($text),
            'nomor_surat' => $this->extractNomorSurat($text),
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return strtoupper(trim($text));
    }

    // ======================
    // RW
    // ======================
    private function extractRw(string $text): ?string
    {
        if (preg_match('/RW[\.\s\:]*([0-9]{1,3})/', $text, $m)) {
            return str_pad($m[1], 3, '0', STR_PAD_LEFT);
        }
        return null;
    }

    private function extractNik(string $text): ?string
    {
        if (preg_match('/\b(\d{16})\b/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function cleanNik(?string $nik): ?string
    {
        return $nik && strlen($nik) === 16 ? $nik : null;
    }

    // ======================
    // NAMA (FIX FINAL)
    // ======================
    private function extractNama(string $text): ?string
    {
        if (preg_match('/NAMA\s*[:\-]?\s*([A-Z][A-Z\s]{3,80})/', $text, $m)) {
            return $this->cleanNama($m[1]);
        }

        if (preg_match('/NAMA\s*[\:\-]?\s*\n+\s*[-–]?\s*([A-Z][A-Z\s]{3,80})/', $text, $m)) {
            return $this->cleanNama($m[1]);
        }

        return null;
    }

    private function cleanNama(string $nama): string
    {
        $stopWords = [
            'TEMPAT',
            'TTL',
            'LAHIR',
            'JENIS',
            'KELAMIN',
            'ALAMAT',
            'NIK',
            'RT',
            'RW',
            'GOL',
            'DARAH',
        ];

        foreach ($stopWords as $word) {
            if (str_contains($nama, $word)) {
                $nama = explode($word, $nama)[0];
            }
        }

        return trim(preg_replace('/\s+/', ' ', $nama));
    }

    private function extractNamaPewaris(string $text): ?string
    {
        if (preg_match('/AHLI\s*WARIS\s*DARI\s*([A-Z][A-Z\s]{3,100})/', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    // =====================================================
    // SYSTEM
    // =====================================================
    private function tesseractManualToString(string $img, int $psm, string $lang): string
    {
        $dir = storage_path('app/private/ocr');
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $out = $dir . '/ocr_' . Str::random(8);
        exec("tesseract {$img} {$out} -l {$lang} --psm {$psm} 2>&1");

        return file_exists($out . '.txt')
            ? trim(file_get_contents($out . '.txt'))
            : '';
    }

    private function convertPdfToPngUsingPoppler(
        string $pdf,
        int $page,
        int $dpi
    ): ?string {
        $dir = storage_path('app/private/ocr');
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $out = $dir . '/page_' . Str::random(8);
        exec("pdftoppm -f {$page} -l {$page} -png -r {$dpi} {$pdf} {$out}");

        $file = "{$out}-{$page}.png";
        return file_exists($file) ? $file : null;
    }
}
