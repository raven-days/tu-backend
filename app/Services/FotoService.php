<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FotoService
{
    public const SISI_MAKS = 1600;
    public const SISI_KECIL = 480;
    private const PIKSEL_MAKS = 30000000; // cegah foto raksasa yang menghabiskan memori

    /** Simpan foto (versi besar dan kecil) di disk privat. */
    public static function simpan(UploadedFile $berkas, string $folder): array
    {
        $path = $berkas->getRealPath();
        $info = @getimagesize($path);

        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new RuntimeException('Format foto tidak didukung. Gunakan JPG atau PNG.');
        }
        if ($info[0] * $info[1] > self::PIKSEL_MAKS) {
            throw new RuntimeException('Resolusi foto terlalu besar. Kecilkan dulu ukurannya.');
        }

        $gambar = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (!$gambar) {
            throw new RuntimeException('Foto tidak dapat dibaca. Mungkin berkasnya rusak.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $gambar = self::putar($gambar, self::orientasi($path));
        }

        $besar = self::ubah($gambar, self::SISI_MAKS);
        $kecil = self::ubah($gambar, self::SISI_KECIL);

        $nama = Str::random(32);
        $pathBesar = "{$folder}/{$nama}.jpg";
        $pathKecil = "{$folder}/{$nama}_kecil.jpg";
        $dataBesar = self::jpeg($besar, 82);

        Storage::disk('local')->put($pathBesar, $dataBesar);
        Storage::disk('local')->put($pathKecil, self::jpeg($kecil, 78));

        return [
            'path' => $pathBesar,
            'kecil' => $pathKecil,
            'ukuran' => strlen($dataBesar),
            'lebar' => imagesx($besar),
            'tinggi' => imagesy($besar),
        ];
    }

    private static function ubah($src, int $maks)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $skala = min(1, $maks / max($w, $h));
        $nw = max(1, (int) round($w * $skala));
        $nh = max(1, (int) round($h * $skala));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG transparan
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private static function jpeg($img, int $kualitas): string
    {
        ob_start();
        imagejpeg($img, null, $kualitas);

        return (string) ob_get_clean();
    }

    private static function putar($img, int $orientasi)
    {
        $sudut = match ($orientasi) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $sudut === 0 ? $img : (imagerotate($img, $sudut, 0) ?: $img);
    }

    /** Baca arah foto dari data EXIF JPEG (tanpa ekstensi exif). 1 = normal. */
    private static function orientasi(string $path): int
    {
        try {
            $f = @fopen($path, 'rb');
            if (!$f) {
                return 1;
            }
            $data = (string) fread($f, 65536);
            fclose($f);

            $len = strlen($data);
            if (substr($data, 0, 2) !== "\xFF\xD8") {
                return 1;
            }

            $pos = 2;
            while ($pos + 4 <= $len) {
                if ($data[$pos] !== "\xFF") {
                    break;
                }
                $marker = ord($data[$pos + 1]);
                $ukuran = unpack('n', substr($data, $pos + 2, 2))[1];

                if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
                    $tiff = $pos + 10;
                    $urutan = substr($data, $tiff, 2);
                    if ($urutan !== 'II' && $urutan !== 'MM') {
                        return 1;
                    }
                    $le = $urutan === 'II';
                    $u16 = fn (int $o) => $o + 2 <= $len ? unpack($le ? 'v' : 'n', substr($data, $o, 2))[1] : 0;
                    $u32 = fn (int $o) => $o + 4 <= $len ? unpack($le ? 'V' : 'N', substr($data, $o, 4))[1] : 0;

                    $ifd = $tiff + $u32($tiff + 4);
                    $jumlah = $u16($ifd);
                    for ($i = 0; $i < $jumlah; $i++) {
                        $e = $ifd + 2 + $i * 12;
                        if ($e + 12 > $len) {
                            break;
                        }
                        if ($u16($e) === 0x0112) {
                            return $u16($e + 8) ?: 1;
                        }
                    }

                    return 1;
                }

                $pos += 2 + $ukuran;
            }
        } catch (\Throwable $e) {
            // abaikan: anggap foto normal
        }

        return 1;
    }
}