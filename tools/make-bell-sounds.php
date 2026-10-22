<?php
// Generates the three built-in bell sounds as small mono WAV files, so the
// project needs no downloaded audio. Run: php tools/make-bell-sounds.php
// Output: bell-pc/sounds/*.wav (the Windows app bundles them; Nexus previews them).

const RATE = 22050;

function wav(string $path, array $samples): void
{
    $peak = max(1e-9, max(array_map('abs', $samples)));
    $data = '';
    foreach ($samples as $s) {
        $data .= pack('v', (int) round(($s / $peak) * 0.8 * 32767) & 0xFFFF);
    }
    $header = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, RATE, RATE * 2, 2, 16)
        . 'data' . pack('V', strlen($data));
    file_put_contents($path, $header . $data);
}

/** A struck bell: inharmonic partials that each fade out. */
function strike(array &$out, float $start, float $freq, float $length, float $gain = 1.0): void
{
    $partials = [[0.5, 0.35, 1.0], [1.0, 1.0, 1.0], [1.19, 0.5, 1.6], [1.56, 0.35, 2.0], [2.0, 0.45, 2.6], [2.51, 0.2, 3.4]];
    $from = (int) ($start * RATE);
    $n = (int) ($length * RATE);
    for ($i = 0; $i < $n; $i++) {
        $t = $i / RATE;
        $v = 0.0;
        foreach ($partials as [$ratio, $amp, $decay]) {
            $v += $amp * sin(2 * M_PI * $freq * $ratio * $t) * exp(-$t * $decay * 2.4);
        }
        $attack = min(1.0, $t / 0.004);
        $idx = $from + $i;
        $out[$idx] = ($out[$idx] ?? 0.0) + $v * $attack * $gain;
    }
}

function silence(float $seconds): array
{
    return array_fill(0, (int) ($seconds * RATE), 0.0);
}

$dir = __DIR__ . '/../bell-pc/sounds';
@mkdir($dir, 0777, true);

// 1. School bell: an electric ringer, short bursts for 4 seconds.
$s = silence(4.0);
for ($i = 0, $n = count($s); $i < $n; $i++) {
    $t = $i / RATE;
    $gate = fmod($t * 14, 1.0) < 0.55 ? 1.0 : 0.0;
    $env = $t > 3.5 ? max(0.0, (4.0 - $t) / 0.5) : 1.0;
    $s[$i] = $gate * $env * (sin(2 * M_PI * 1750 * $t) + 0.6 * sin(2 * M_PI * 2350 * $t) + 0.3 * sin(2 * M_PI * 3500 * $t));
}
wav("$dir/bel-sekolah.wav", $s);

// 2. Chime: four descending notes.
$s = silence(5.0);
foreach ([[0.0, 784.0], [0.9, 659.0], [1.8, 523.0], [2.7, 392.0]] as [$at, $f]) {
    strike($s, $at, $f, 2.2);
}
wav("$dir/denting.wav", $s);

// 3. Alert: two alternating tones, three times.
$s = silence(3.2);
foreach ([0.0, 0.9, 1.8] as $at) {
    strike($s, $at, 880.0, 0.8, 1.0);
    strike($s, $at + 0.4, 660.0, 0.8, 1.0);
}
wav("$dir/peringatan.wav", $s);

echo "done\n";
