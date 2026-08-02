<?php

declare(strict_types=1);

namespace Drupal\fashion_video;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\Process\Process;

/**
 * Assembles the final fashion video with ffmpeg.
 *
 * Phase 1a (step a) scope: take the D-ID "talking head" clip, normalize it onto
 * the canonical 720x1280 portrait canvas, and lay the chosen background song
 * under the voice — ducked via a sidechain compressor and faded out at the end.
 *
 * The ffmpeg binary is invoked out-of-process (Symfony Process). All work
 * happens in a local temp dir: s3fs stream wrappers aren't seekable paths
 * ffmpeg can open, so callers pass raw bytes and we read the result back as
 * bytes for the uploader to store.
 */
final class VideoAssembler {

  /** Canonical output canvas (portrait 720p) and frame rate. */
  private const WIDTH = 720;
  private const HEIGHT = 1280;
  private const FPS = 30;

  /** Background-song level before ducking (0..1) and end fade length. */
  private const SONG_VOLUME = 0.35;
  private const FADE_OUT_SEC = 1.2;

  /** Per-still Ken Burns segment length (seconds) and its zoom ceiling. */
  private const SEGMENT_SEC = 3.0;
  private const ZOOM_MAX = 1.18;

  /** Hard ceiling on a single ffmpeg run. */
  private const FFMPEG_TIMEOUT = 180;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Whether ffmpeg is available on this host.
   */
  public function isConfigured(): bool {
    try {
      $process = new Process(['ffmpeg', '-hide_banner', '-version']);
      $process->setTimeout(10);
      $process->run();
      return $process->isSuccessful();
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * Normalizes the talking clip to 720x1280 and lays the song under the voice.
   *
   * @param string $clipBytes
   *   Raw MP4 bytes of the D-ID talking clip (must carry a voice audio track).
   * @param string|null $songBytes
   *   Raw bytes of the background song, or NULL to skip the music bed.
   * @param string $songExt
   *   The song file extension (used only for the temp filename).
   *
   * @return string|null
   *   The assembled MP4 bytes, or NULL on failure.
   */
  public function assembleTalkingClip(string $clipBytes, ?string $songBytes, string $songExt = 'mp3'): ?string {
    $dir = rtrim($this->fileSystem->getTempDirectory(), '/') . '/fv-' . uniqid('', TRUE);
    if (!@mkdir($dir, 0775, TRUE) && !is_dir($dir)) {
      $this->logger->warning('VideoAssembler: could not create temp dir @dir', ['@dir' => $dir]);
      return NULL;
    }

    $clipPath = $dir . '/talk.mp4';
    $outPath = $dir . '/final.mp4';
    $songPath = NULL;

    try {
      if (file_put_contents($clipPath, $clipBytes) === FALSE) {
        return NULL;
      }
      if ($songBytes !== NULL && $songBytes !== '') {
        $safeExt = preg_replace('/[^a-z0-9]/i', '', $songExt) ?: 'mp3';
        $songPath = $dir . '/song.' . $safeExt;
        if (file_put_contents($songPath, $songBytes) === FALSE) {
          $songPath = NULL;
        }
      }

      $duration = $this->probeDuration($clipPath);
      $command = $this->buildCommand($clipPath, $songPath, $outPath, $duration);

      $process = new Process($command);
      $process->setTimeout(self::FFMPEG_TIMEOUT);
      $process->run();
      if (!$process->isSuccessful()) {
        $this->logger->warning('ffmpeg assembly failed: @err', [
          '@err' => substr($process->getErrorOutput(), -1200),
        ]);
        return NULL;
      }

      $bytes = @file_get_contents($outPath);
      return ($bytes === FALSE || $bytes === '') ? NULL : $bytes;
    }
    catch (\Throwable $e) {
      $this->logger->warning('VideoAssembler error: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
    finally {
      foreach ([$clipPath, $songPath, $outPath] as $file) {
        if (is_string($file) && is_file($file)) {
          @unlink($file);
        }
      }
      @rmdir($dir);
    }
  }

  /**
   * Assembles the full montage: Ken Burns pan/zoom clips of the runway stills
   * wrapped around the talking clip, with the song under the whole thing.
   *
   * Segment order is [intro stills…, talking clip, outro still]: all but the
   * last still play before the talk, the last plays after. The voice is delayed
   * to line up with the talk segment; the song ducks under it and swells back
   * for the outro, fading out at the very end.
   *
   * Falls back to ::assembleTalkingClip() when there are no stills.
   *
   * @param string $clipBytes
   *   Raw MP4 bytes of the D-ID talking clip (carries the voice track).
   * @param string[] $stillsBytes
   *   Raw bytes of the runway still images (PNG/JPEG), in display order.
   * @param string|null $songBytes
   *   Raw song bytes, or NULL to skip the music bed.
   * @param string $songExt
   *   Song file extension (temp filename only).
   *
   * @return string|null
   *   The assembled MP4 bytes, or NULL on failure.
   */
  public function assembleMontage(string $clipBytes, array $stillsBytes, ?string $songBytes, string $songExt = 'mp3'): ?string {
    $stillsBytes = array_values(array_filter($stillsBytes, static fn ($b) => is_string($b) && $b !== ''));
    if (!$stillsBytes) {
      return $this->assembleTalkingClip($clipBytes, $songBytes, $songExt);
    }

    $dir = rtrim($this->fileSystem->getTempDirectory(), '/') . '/fv-' . uniqid('', TRUE);
    if (!@mkdir($dir, 0775, TRUE) && !is_dir($dir)) {
      $this->logger->warning('VideoAssembler: could not create temp dir @dir', ['@dir' => $dir]);
      return NULL;
    }

    try {
      $clipPath = $dir . '/talk.mp4';
      if (file_put_contents($clipPath, $clipBytes) === FALSE) {
        return NULL;
      }

      $songPath = NULL;
      if ($songBytes !== NULL && $songBytes !== '') {
        $safeExt = preg_replace('/[^a-z0-9]/i', '', $songExt) ?: 'mp3';
        $songPath = $dir . '/song.' . $safeExt;
        if (file_put_contents($songPath, $songBytes) === FALSE) {
          $songPath = NULL;
        }
      }

      // Write stills; the last one is the outro, the rest are the intro.
      $stillPaths = [];
      foreach ($stillsBytes as $i => $bytes) {
        $path = $dir . '/still' . $i . '.img';
        if (file_put_contents($path, $bytes) === FALSE) {
          return NULL;
        }
        $stillPaths[] = $path;
      }
      $outroStill = array_pop($stillPaths);
      $introStills = $stillPaths;

      $talkDuration = $this->probeDuration($clipPath) ?? 8.0;

      // Build the ordered video segments (all normalized to the same canvas).
      $segments = [];
      foreach ($introStills as $idx => $stillPath) {
        $seg = $dir . '/seg_intro' . $idx . '.mp4';
        if (!$this->makePanClip($stillPath, $seg)) {
          return NULL;
        }
        $segments[] = $seg;
      }
      $talkSeg = $dir . '/seg_talk.mp4';
      if (!$this->normalizeTalkVideo($clipPath, $talkSeg)) {
        return NULL;
      }
      $segments[] = $talkSeg;
      $outroSeg = $dir . '/seg_outro.mp4';
      if (!$this->makePanClip($outroStill, $outroSeg)) {
        return NULL;
      }
      $segments[] = $outroSeg;

      $stillCount = count($introStills) + 1;
      $total = $stillCount * self::SEGMENT_SEC + $talkDuration;
      $introMs = (int) round(count($introStills) * self::SEGMENT_SEC * 1000);

      $outPath = $dir . '/final.mp4';
      $command = $this->buildMontageCommand($segments, $clipPath, $songPath, $outPath, $introMs, $total);
      if (!$this->runFfmpeg($command, self::FFMPEG_TIMEOUT)) {
        return NULL;
      }

      $bytes = @file_get_contents($outPath);
      return ($bytes === FALSE || $bytes === '') ? NULL : $bytes;
    }
    catch (\Throwable $e) {
      $this->logger->warning('VideoAssembler montage error: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
    finally {
      $this->cleanupDir($dir);
    }
  }

  /**
   * Renders one Ken Burns pan/zoom clip (video only) from a still image.
   */
  private function makePanClip(string $stillPath, string $outPath): bool {
    $frames = (int) round(self::SEGMENT_SEC * self::FPS);
    $filter = sprintf(
      '[0:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,'
      . "zoompan=z='min(zoom+0.0012,%.3f)':d=%d:"
      . "x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=%dx%d:fps=%d,"
      . 'setsar=1,format=yuv420p[v]',
      self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT,
      self::ZOOM_MAX, $frames, self::WIDTH, self::HEIGHT, self::FPS
    );

    return $this->runFfmpeg([
      'ffmpeg', '-y', '-loop', '1', '-i', $stillPath,
      '-filter_complex', $filter,
      '-map', '[v]', '-frames:v', (string) $frames, '-r', (string) self::FPS,
      '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p', '-an',
      $outPath,
    ], 90);
  }

  /**
   * Re-renders the talking clip's video onto the canonical canvas (no audio).
   */
  private function normalizeTalkVideo(string $clipPath, string $outPath): bool {
    $scale = sprintf(
      'scale=%d:%d:force_original_aspect_ratio=decrease,'
      . 'pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=%d,format=yuv420p',
      self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT, self::FPS
    );

    return $this->runFfmpeg([
      'ffmpeg', '-y', '-i', $clipPath, '-an', '-vf', $scale, '-r', (string) self::FPS,
      '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
      $outPath,
    ], 90);
  }

  /**
   * Builds the final montage ffmpeg command: concat the video segments and mix
   * the (delayed, ducked) voice with the background song.
   *
   * @param string[] $segments
   *   Ordered video-only segment paths.
   *
   * @return string[]
   */
  private function buildMontageCommand(array $segments, string $clipPath, ?string $songPath, string $outPath, int $introMs, float $total): array {
    $inputs = [];
    foreach ($segments as $seg) {
      $inputs[] = '-i';
      $inputs[] = $seg;
    }
    // The talking clip again, this time only as the voice audio source.
    $voiceIdx = count($segments);
    $inputs[] = '-i';
    $inputs[] = $clipPath;
    if ($songPath !== NULL) {
      $inputs[] = '-stream_loop';
      $inputs[] = '-1';
      $inputs[] = '-i';
      $inputs[] = $songPath;
    }

    $concat = '';
    for ($i = 0; $i < count($segments); $i++) {
      $concat .= '[' . $i . ':v]';
    }
    $concat .= 'concat=n=' . count($segments) . ':v=1:a=0[vout]';

    $fadeStart = max(0.0, $total - self::FADE_OUT_SEC);

    if ($songPath !== NULL) {
      $songIdx = $voiceIdx + 1;
      $filter = $concat . ';'
        // Split the delayed voice: one copy drives the sidechain, the other is
        // mixed in (a filter output label can only be consumed once).
        . sprintf('[%d:a]adelay=%d|%d,asplit=2[voice_main][voice_sc];', $voiceIdx, $introMs, $introMs)
        . sprintf('[%d:a]volume=%.3f[music];', $songIdx, self::SONG_VOLUME)
        . '[music][voice_sc]sidechaincompress=threshold=0.05:ratio=6:attack=5:release=300[duck];'
        . sprintf(
          '[voice_main][duck]amix=inputs=2:duration=longest:dropout_transition=0,afade=t=out:st=%.3f:d=%.3f[aout]',
          $fadeStart, self::FADE_OUT_SEC
        );
    }
    else {
      // No song: just place the voice at the talk offset over silence.
      $filter = $concat . ';'
        . sprintf('[%d:a]adelay=%d|%d,apad[aout]', $voiceIdx, $introMs, $introMs);
    }

    return array_merge(
      ['ffmpeg', '-y'],
      $inputs,
      [
        '-filter_complex', $filter,
        '-map', '[vout]', '-map', '[aout]',
        '-t', sprintf('%.3f', $total),
        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
        '-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart',
        $outPath,
      ],
    );
  }

  /**
   * Runs an ffmpeg command, logging stderr on failure.
   *
   * @param string[] $command
   */
  private function runFfmpeg(array $command, int $timeout): bool {
    try {
      $process = new Process($command);
      $process->setTimeout($timeout);
      $process->run();
      if (!$process->isSuccessful()) {
        $this->logger->warning('ffmpeg failed: @err', [
          '@err' => substr($process->getErrorOutput(), -1200),
        ]);
        return FALSE;
      }
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->warning('ffmpeg error: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Recursively removes a temp working directory.
   */
  private function cleanupDir(string $dir): void {
    if (!is_dir($dir)) {
      return;
    }
    foreach ((array) @scandir($dir) as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $path = $dir . '/' . $entry;
      if (is_file($path)) {
        @unlink($path);
      }
    }
    @rmdir($dir);
  }

  /**
   * Reads a media file's duration (seconds) via ffprobe.
   */
  private function probeDuration(string $path): ?float {
    try {
      $process = new Process([
        'ffprobe', '-v', 'error',
        '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1',
        $path,
      ]);
      $process->setTimeout(30);
      $process->run();
      if ($process->isSuccessful()) {
        $duration = (float) trim($process->getOutput());
        return $duration > 0 ? $duration : NULL;
      }
    }
    catch (\Throwable $e) {
      // Fall through to NULL (fade is simply skipped).
    }
    return NULL;
  }

  /**
   * Builds the ffmpeg argument vector.
   *
   * @return string[]
   */
  private function buildCommand(string $clipPath, ?string $songPath, string $outPath, ?float $duration): array {
    $scale = sprintf(
      'scale=%d:%d:force_original_aspect_ratio=decrease,'
      . 'pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=%d,format=yuv420p',
      self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT, self::FPS
    );

    $videoCodec = [
      '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
    ];

    // No song: just normalize the canvas and keep the voice track.
    if ($songPath === NULL) {
      return array_merge(
        ['ffmpeg', '-y', '-i', $clipPath, '-vf', $scale],
        $videoCodec,
        ['-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart', $outPath],
      );
    }

    // Duck the (looped) song under the voice, fade the mix out at the end.
    $fadeStart = ($duration !== NULL && $duration > self::FADE_OUT_SEC)
      ? $duration - self::FADE_OUT_SEC
      : 0.0;

    $filterComplex = sprintf(
      '[0:v]%s[v];'
      . '[0:a]asplit=2[a1][a2];'
      . '[1:a]volume=%.3f[music];'
      . '[music][a2]sidechaincompress=threshold=0.05:ratio=6:attack=5:release=300[duck];'
      . '[a1][duck]amix=inputs=2:duration=first:dropout_transition=0,'
      . 'afade=t=out:st=%.3f:d=%.3f[aout]',
      $scale, self::SONG_VOLUME, $fadeStart, self::FADE_OUT_SEC
    );

    return array_merge(
      [
        'ffmpeg', '-y',
        '-i', $clipPath,
        '-stream_loop', '-1', '-i', $songPath,
        '-filter_complex', $filterComplex,
        '-map', '[v]', '-map', '[aout]',
      ],
      $videoCodec,
      ['-c:a', 'aac', '-b:a', '160k', '-shortest', '-movflags', '+faststart', $outPath],
    );
  }

}
