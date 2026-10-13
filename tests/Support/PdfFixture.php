<?php

namespace LucasBarros\LaravelFileManager\Tests\Support;

class PdfFixture
{
    /**
     * Cria um PDF real e suficientemente grande para os testes
     * de otimização.
     */
    public static function create(): string
    {
        $directory = sys_get_temp_dir()
            . '/file-manager-pdf-fixture-' . uniqid();

        if (! mkdir($directory, 0755, true)) {
            throw new \RuntimeException(
                'Failed to create PDF fixture directory.'
            );
        }

        $images = [];

        try {
            for ($page = 1; $page <= 5; $page++) {
                $imagePath = $directory . "/image-{$page}.jpg";

                self::createImage($imagePath, $page);

                $images[] = $imagePath;
            }

            $pdfPath = $directory . '/fixture.pdf';

            self::createPdf(
                $pdfPath,
                $images
            );

            return $pdfPath;
        } catch (\Throwable $exception) {
            self::cleanup($directory);

            throw $exception;
        }
    }

    /**
     * Remove o PDF e os arquivos temporários associados.
     */
    public static function cleanup(string $pdfPath): void
    {
        $directory = dirname($pdfPath);

        self::removeDirectory($directory);
    }

    private static function createImage(
        string $path,
        int $page
    ): void {
        $width = 1600;
        $height = 2200;

        $image = imagecreatetruecolor(
            $width,
            $height
        );

        if ($image === false) {
            throw new \RuntimeException(
                'Failed to create PDF fixture image.'
            );
        }

        try {
            /*
             * Gera conteúdo visual variado e determinístico.
             *
             * O ruído dificulta a compressão do JPEG original,
             * tornando o PDF adequado para testar a redução.
             */
            for ($y = 0; $y < $height; $y += 4) {
                for ($x = 0; $x < $width; $x += 4) {
                    $red = (
                        ($x * 17)
                        + ($y * 13)
                        + ($page * 31)
                    ) % 256;

                    $green = (
                        ($x * 7)
                        + ($y * 23)
                        + ($page * 47)
                    ) % 256;

                    $blue = (
                        ($x * 29)
                        + ($y * 11)
                        + ($page * 19)
                    ) % 256;

                    $color = imagecolorallocate(
                        $image,
                        $red,
                        $green,
                        $blue
                    );

                    imagefilledrectangle(
                        $image,
                        $x,
                        $y,
                        min($x + 3, $width - 1),
                        min($y + 3, $height - 1),
                        $color
                    );
                }
            }

            if (! imagejpeg(
                $image,
                $path,
                90
            )) {
                throw new \RuntimeException(
                    'Failed to create PDF fixture JPEG.'
                );
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Cria um PDF simples contendo uma imagem JPEG por página.
     *
     * @param array<int, string> $images
     */
    private static function createPdf(
        string $path,
        array $images
    ): void {
        $objects = [];
        $pages = [];
        $imageObjects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageTree = [
            '/Type /Pages',
            '/Kids [',
        ];

        foreach ($images as $index => $imagePath) {
            $imageObjectNumber = 5 + ($index * 3);
            $contentObjectNumber = $imageObjectNumber + 1;
            $pageObjectNumber = $imageObjectNumber + 2;

            $imageData = file_get_contents($imagePath);

            if ($imageData === false) {
                throw new \RuntimeException(
                    'Failed to read PDF fixture image.'
                );
            }

            $imageInfo = getimagesize($imagePath);

            if ($imageInfo === false) {
                throw new \RuntimeException(
                    'Failed to inspect PDF fixture image.'
                );
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];

            $objects[$imageObjectNumber] = [
                'dictionary' => implode("\n", [
                    '/Type /XObject',
                    '/Subtype /Image',
                    '/Width ' . $width,
                    '/Height ' . $height,
                    '/ColorSpace /DeviceRGB',
                    '/BitsPerComponent 8',
                    '/Filter /DCTDecode',
                ]),
                'stream' => $imageData,
            ];

            $content = implode("\n", [
                'q',
                '595 0 0 842 0 0 cm',
                '/Im1 Do',
                'Q',
            ]);

            $objects[$contentObjectNumber] = [
                'dictionary' => '/Length ' . strlen($content),
                'stream' => $content,
            ];

            $objects[$pageObjectNumber] = implode("\n", [
                '<< /Type /Page',
                '/Parent 2 0 R',
                '/MediaBox [0 0 595 842]',
                '/Resources <<',
                '/XObject << /Im1 '
                    . $imageObjectNumber
                    . ' 0 R >>',
                '>>',
                '/Contents '
                    . $contentObjectNumber
                    . ' 0 R',
                '>>',
            ]);

            $pageTree[] = $pageObjectNumber . ' 0 R';

            $pages[] = $pageObjectNumber;
            $imageObjects[] = $imageObjectNumber;
        }

        $pageTree[] = ']';
        $pageTree[] = '/Count ' . count($pages);
        $pageTree[] = '>>';

        $objects[1] = implode("\n", $pageTree);

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);

            $pdf .= $number . " 0 obj\n";

            if (is_array($object)) {
                $pdf .= '<< ' . $object['dictionary'] . " >>\n";
                $pdf .= "stream\n";
                $pdf .= $object['stream'];
                $pdf .= "\nendstream\n";
            } else {
                $pdf .= $object . "\n";
            }

            $pdf .= "endobj\n";
        }

        $xrefOffset = strlen($pdf);

        $maxObject = max(array_keys($objects));

        $pdf .= "xref\n";
        $pdf .= "0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObject; $number++) {
            $offset = $offsets[$number] ?? 0;

            $pdf .= sprintf(
                "%010d 00000 n \n",
                $offset
            );
        }

        $pdf .= "trailer\n";
        $pdf .= "<<\n";
        $pdf .= "/Size " . ($maxObject + 1) . "\n";
        $pdf .= "/Root 1 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= "%%EOF\n";

        if (file_put_contents($path, $pdf) === false) {
            throw new \RuntimeException(
                'Failed to create PDF fixture.'
            );
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = scandir($directory);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    public static function createMinimal(): string
    {
        $directory = sys_get_temp_dir()
            . '/file-manager-pdf-fixture-' . uniqid();

        if (! mkdir($directory, 0755, true)) {
            throw new \RuntimeException(
                'Failed to create PDF fixture directory.'
            );
        }

        try {
            $pdfPath = $directory . '/fixture.pdf';

            $pdf = "%PDF-1.4\n";

            $offsets = [];

            $objects = [
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page'
                    . ' /Parent 2 0 R'
                    . ' /MediaBox [0 0 595 842]'
                    . ' /Contents 4 0 R'
                    . ' /Resources << >>'
                    . ' >>',
                4 => [
                    'dictionary' => '<< /Length 0 >>',
                    'stream' => '',
                ],
            ];

            foreach ($objects as $number => $object) {
                $offsets[$number] = strlen($pdf);

                $pdf .= $number . " 0 obj\n";

                if (is_array($object)) {
                    $pdf .= $object['dictionary'] . "\n";
                    $pdf .= "stream\n";
                    $pdf .= $object['stream'];
                    $pdf .= "\nendstream\n";
                } else {
                    $pdf .= $object . "\n";
                }

                $pdf .= "endobj\n";
            }

            $xrefOffset = strlen($pdf);

            $pdf .= "xref\n";
            $pdf .= "0 5\n";
            $pdf .= "0000000000 65535 f \n";

            for ($number = 1; $number <= 4; $number++) {
                $pdf .= sprintf(
                    "%010d 00000 n \n",
                    $offsets[$number]
                );
            }

            $pdf .= "trailer\n";
            $pdf .= "<< /Size 5 /Root 1 0 R >>\n";
            $pdf .= "startxref\n";
            $pdf .= $xrefOffset . "\n";
            $pdf .= "%%EOF\n";

            if (file_put_contents($pdfPath, $pdf) === false) {
                throw new \RuntimeException(
                    'Failed to create minimal PDF fixture.'
                );
            }

            return $pdfPath;
        } catch (\Throwable $exception) {
            self::cleanup($directory . '/fixture.pdf');

            throw $exception;
        }
    }
}