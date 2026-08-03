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
  private const SONG_VOLUME = 0.44;
  private const FADE_OUT_SEC = 1.2;

  /** Per-still Ken Burns segment length (seconds) and its zoom ceiling. */
  private const SEGMENT_SEC = 3.0;
  private const ZOOM_MAX = 1.18;

  /** "Show" montage timing: white camera-flash, the still-B hold, fades. */
  private const FLASH_SEC = 0.2;
  private const STILLB_SEC = 2.5;
  /** Crossfade (dissolve) length from the lip-sync clip into the final still. */
  private const XFADE_SEC = 1.0;
  private const FLASH_FADE_SEC = 0.4;

  /**
   * SFX layer levels (0..1) and shaping. These mix on top of the balanced
   * voice+song bed, so keep them modest to avoid clipping.
   */
  private const SFX_CROWD_VOLUME = 0.32;
  private const SFX_FLASH_VOLUME = 0.7;
  private const SFX_WORDS_VOLUME = 0.85;
  private const SFX_APPLAUSE_VOLUME = 0.55;
  /**
   * When the "words" sting finishes, relative to the lip-sync's end. Positive =
   * ends after the lip-sync; negative = ends before it (so the sting overlaps
   * and plays under the tail of the lip-sync).
   */
  private const SFX_WORDS_TAIL_SEC = -1.5;
  private const SFX_CROWD_FADE_SEC = 0.3;
  private const SFX_APPLAUSE_FADE_IN_SEC = 0.6;
  private const SFX_APPLAUSE_FADE_OUT_SEC = 1.0;

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
   * Assembles the "runway show" montage in a fixed creative order:
   *
   *   1. Ken Burns zoom of still A
   *   2. the motion clip (generated from still B)
   *   3. a white camera-flash
   *   4. fade-from-white into still B (brief Ken Burns hold)
   *   5. the lip-sync talking clip (carries the voice)
   *   6. fade into a Ken Burns zoom of still C
   *
   * The chosen song plays underneath the whole thing, ducked under the voice
   * during the lip-sync segment and faded out at the end. Falls back to
   * ::assembleTalkingClip() if the motion clip can't be prepared.
   *
   * @param string $talkBytes
   *   Raw MP4 bytes of the lip-sync clip (carries the voice track).
   * @param string $motionBytes
   *   Raw MP4 bytes of the fal motion clip (video only is used).
   * @param string $stillA
   *   Bytes of the opening still (Ken Burns).
   * @param string $stillB
   *   Bytes of the motion clip's source still (shown after the flash).
   * @param string $stillC
   *   Bytes of the closing still (Ken Burns).
   * @param string|null $songBytes
   *   Raw song bytes, or NULL to skip the music bed.
   * @param string $songExt
   *   Song file extension (temp filename only).
   * @param array<string, array{0: string, 1: string}|null> $sfx
   *   Map of SFX category (flash/crowd/words/applause) => [bytes, ext], or NULL
   *   per category. Each present clip is layered in at its fixed cue point.
   *
   * @return string|null
   *   The assembled MP4 bytes, or NULL on failure.
   */
  public function assembleShow(string $talkBytes, string $motionBytes, string $stillA, string $stillB, string $stillC, ?string $songBytes, string $songExt = 'mp3', array $sfx = []): ?string {
    $dir = rtrim($this->fileSystem->getTempDirectory(), '/') . '/fv-' . uniqid('', TRUE);
    if (!@mkdir($dir, 0775, TRUE) && !is_dir($dir)) {
      $this->logger->warning('VideoAssembler: could not create temp dir @dir', ['@dir' => $dir]);
      return NULL;
    }

    try {
      $talkPath = $dir . '/talk.mp4';
      $motionPath = $dir . '/motion.mp4';
      $stillAPath = $dir . '/stillA.img';
      $stillBPath = $dir . '/stillB.img';
      $stillCPath = $dir . '/stillC.img';
      if (
        file_put_contents($talkPath, $talkBytes) === FALSE
        || file_put_contents($motionPath, $motionBytes) === FALSE
        || file_put_contents($stillAPath, $stillA) === FALSE
        || file_put_contents($stillBPath, $stillB) === FALSE
        || file_put_contents($stillCPath, $stillC) === FALSE
      ) {
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

      $motionDur = $this->probeDuration($motionPath) ?? 5.0;
      $talkDur = $this->probeDuration($talkPath) ?? 8.0;

      // Build the ordered, canvas-normalized video segments.
      $segA = $dir . '/seg_a.mp4';
      $segMotion = $dir . '/seg_motion.mp4';
      $segFlash = $dir . '/seg_flash.mp4';
      $segB = $dir . '/seg_b.mp4';
      $segTalk = $dir . '/seg_talk.mp4';
      $segC = $dir . '/seg_c.mp4';

      if (
        !$this->makePanClip($stillAPath, $segA)
        || !$this->normalizeTalkVideo($motionPath, $segMotion)
        || !$this->makeWhiteClip($segFlash, self::FLASH_SEC)
        || !$this->makePanClip($stillBPath, $segB, self::STILLB_SEC, 'white', self::FLASH_FADE_SEC)
        || !$this->normalizeTalkVideo($talkPath, $segTalk)
        // No black fade-in: the closing still is dissolved into via xfade below.
        || !$this->makePanClip($stillCPath, $segC)
      ) {
        return NULL;
      }

      $segments = [$segA, $segMotion, $segFlash, $segB, $segTalk, $segC];

      // The voice must start when the lip-sync segment does.
      $voiceOffset = self::SEGMENT_SEC + $motionDur + self::FLASH_SEC + self::STILLB_SEC;
      // The closing still crossfades in over the last XFADE_SEC of the lip-sync,
      // so the montage is XFADE_SEC shorter than a hard cut would be.
      $crossfadeOffset = $voiceOffset + $talkDur - self::XFADE_SEC;
      $total = $voiceOffset + $talkDur + self::SEGMENT_SEC - self::XFADE_SEC;
      $voiceOffsetMs = (int) round($voiceOffset * 1000);

      // Position each SFX clip on the montage timeline.
      $sfxCues = $this->buildSfxCues($dir, $sfx, $motionDur, $talkDur, $voiceOffset);

      $outPath = $dir . '/final.mp4';
      $command = $this->buildMontageCommand($segments, $talkPath, $songPath, $outPath, $voiceOffsetMs, $total, $sfxCues, $crossfadeOffset);
      if (!$this->runFfmpeg($command, self::FFMPEG_TIMEOUT)) {
        return NULL;
      }

      $bytes = @file_get_contents($outPath);
      return ($bytes === FALSE || $bytes === '') ? NULL : $bytes;
    }
    catch (\Throwable $e) {
      $this->logger->warning('VideoAssembler show error: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
    finally {
      $this->cleanupDir($dir);
    }
  }

  /**
   * Turns the resolved SFX map into positioned cue descriptors for the mixer.
   *
   * Cue points (t=0 is the start of the whole video):
   *  - crowd:    over the motion clip           [SEGMENT_SEC .. +motionDur]
   *  - flash:    on the white camera-flash beat  (SEGMENT_SEC + motionDur)
   *  - words:    ends SFX_WORDS_TAIL_SEC after the lip-sync ends
   *  - applause: over the closing still (from the crossfade in), fading in/out
   *
   * Each SFX blob is written to a temp file in $dir. Categories with no clip are
   * skipped. Returns a list of cue arrays consumed by ::buildMontageCommand().
   *
   * @param array<string, array{0: string, 1: string}|null> $sfx
   *
   * @return array<int, array{path: string, delayMs: int, volume: float, loop: bool, trim: float|null, fadeIn: float, fadeOut: float}>
   */
  private function buildSfxCues(string $dir, array $sfx, float $motionDur, float $talkDur, float $voiceOffset): array {
    $talkEnd = $voiceOffset + $talkDur;

    // [category, delaySec, volume, loop, trimSec|null, fadeIn, fadeOut].
    // `words` gets a placeholder delay; it depends on the clip's own length and
    // is finalized below once probed.
    $specs = [
      'crowd' => [self::SEGMENT_SEC, self::SFX_CROWD_VOLUME, TRUE, $motionDur, self::SFX_CROWD_FADE_SEC, self::SFX_CROWD_FADE_SEC],
      'flash' => [self::SEGMENT_SEC + $motionDur, self::SFX_FLASH_VOLUME, FALSE, NULL, 0.0, 0.0],
      'words' => [$talkEnd, self::SFX_WORDS_VOLUME, FALSE, NULL, 0.0, 0.0],
      'applause' => [$talkEnd - self::XFADE_SEC, self::SFX_APPLAUSE_VOLUME, TRUE, self::SEGMENT_SEC, self::SFX_APPLAUSE_FADE_IN_SEC, self::SFX_APPLAUSE_FADE_OUT_SEC],
    ];

    $cues = [];
    foreach ($specs as $category => [$delaySec, $volume, $loop, $trim, $fadeIn, $fadeOut]) {
      $entry = $sfx[$category] ?? NULL;
      if (!is_array($entry) || !isset($entry[0]) || $entry[0] === '') {
        continue;
      }
      [$bytes, $ext] = $entry;
      $safeExt = preg_replace('/[^a-z0-9]/i', '', (string) $ext) ?: 'mp3';
      $path = $dir . '/sfx_' . $category . '.' . $safeExt;
      if (file_put_contents($path, $bytes) === FALSE) {
        continue;
      }

      // "words" must *end* SFX_WORDS_TAIL_SEC after the lip-sync, so back its
      // start off by its own duration.
      if ($category === 'words') {
        $wordsDur = $this->probeDuration($path) ?? 1.5;
        $delaySec = max(0.0, $talkEnd + self::SFX_WORDS_TAIL_SEC - $wordsDur);
      }

      $cues[] = [
        'path' => $path,
        'delayMs' => (int) round($delaySec * 1000),
        'volume' => (float) $volume,
        'loop' => (bool) $loop,
        'trim' => $trim,
        'fadeIn' => (float) $fadeIn,
        'fadeOut' => (float) $fadeOut,
      ];
    }

    return $cues;
  }

  /**
   * Renders one Ken Burns pan/zoom clip (video only) from a still image.
   *
   * @param float $seconds
   *   Clip length; defaults to the standard Ken Burns segment.
   * @param string|null $fadeColor
   *   When set (e.g. "white" or "black"), fade the clip in from this color.
   * @param float $fadeDur
   *   Fade-in duration in seconds (ignored when $fadeColor is NULL).
   */
  private function makePanClip(string $stillPath, string $outPath, float $seconds = self::SEGMENT_SEC, ?string $fadeColor = NULL, float $fadeDur = 0.0): bool {
    $frames = (int) round($seconds * self::FPS);
    $fade = ($fadeColor !== NULL && $fadeDur > 0)
      ? sprintf(',fade=t=in:st=0:d=%.3f:color=%s', $fadeDur, $fadeColor)
      : '';
    $filter = sprintf(
      '[0:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,'
      . "zoompan=z='min(zoom+0.0012,%.3f)':d=%d:"
      . "x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=%dx%d:fps=%d,"
      . 'setsar=1%s,format=yuv420p[v]',
      self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT,
      self::ZOOM_MAX, $frames, self::WIDTH, self::HEIGHT, self::FPS, $fade
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
   * Renders a solid-white clip (video only) — used as the camera-flash beat.
   */
  private function makeWhiteClip(string $outPath, float $seconds): bool {
    return $this->runFfmpeg([
      'ffmpeg', '-y', '-f', 'lavfi', '-i',
      sprintf('color=c=white:s=%dx%d:r=%d:d=%.3f', self::WIDTH, self::HEIGHT, self::FPS, $seconds),
      '-vf', 'setsar=1,format=yuv420p',
      '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p', '-an',
      $outPath,
    ], 30);
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
  private function buildMontageCommand(array $segments, string $clipPath, ?string $songPath, string $outPath, int $introMs, float $total, array $sfxCues = [], ?float $crossfadeOffset = NULL): array {
    $inputs = [];
    foreach ($segments as $seg) {
      $inputs[] = '-i';
      $inputs[] = $seg;
    }
    // The talking clip again, this time only as the voice audio source.
    $voiceIdx = count($segments);
    $inputs[] = '-i';
    $inputs[] = $clipPath;
    $songIdx = NULL;
    if ($songPath !== NULL) {
      $songIdx = $voiceIdx + 1;
      $inputs[] = '-stream_loop';
      $inputs[] = '-1';
      $inputs[] = '-i';
      $inputs[] = $songPath;
    }

    // SFX inputs come after the voice (+ optional song). Looping clips (crowd,
    // applause) get -stream_loop so they always fill their window.
    $sfxInputs = [];
    $nextIdx = $voiceIdx + 1 + ($songPath !== NULL ? 1 : 0);
    foreach ($sfxCues as $cue) {
      if (!empty($cue['loop'])) {
        $inputs[] = '-stream_loop';
        $inputs[] = '-1';
      }
      $inputs[] = '-i';
      $inputs[] = $cue['path'];
      $sfxInputs[] = ['idx' => $nextIdx, 'cue' => $cue];
      $nextIdx++;
    }

    $segCount = count($segments);
    // Video track: normally a straight concat of every segment. When a crossfade
    // offset is given (the "show" montage) the final segment is dissolved into
    // rather than hard-cut — concat everything before it, then xfade the last.
    if ($crossfadeOffset === NULL || $segCount < 2) {
      $videoFilter = '';
      for ($i = 0; $i < $segCount; $i++) {
        $videoFilter .= '[' . $i . ':v]';
      }
      $videoFilter .= 'concat=n=' . $segCount . ':v=1:a=0[vout]';
    }
    else {
      $lastIdx = $segCount - 1;
      $headCount = $segCount - 1;
      // xfade requires both inputs to share a timebase, but concat's output
      // timebase differs from a single clip's — pin both to AVTB before fading.
      $videoFilter = '';
      if ($headCount >= 2) {
        for ($i = 0; $i < $headCount; $i++) {
          $videoFilter .= '[' . $i . ':v]';
        }
        $videoFilter .= 'concat=n=' . $headCount . ':v=1:a=0,settb=AVTB[xhead];';
      }
      else {
        $videoFilter .= '[0:v]settb=AVTB[xhead];';
      }
      $videoFilter .= sprintf(
        '[%d:v]settb=AVTB[xtail];[xhead][xtail]xfade=transition=fade:duration=%.3f:offset=%.3f,format=yuv420p[vout]',
        $lastIdx, self::XFADE_SEC, $crossfadeOffset
      );
    }

    $fadeStart = max(0.0, $total - self::FADE_OUT_SEC);
    $hasSfx = $sfxInputs !== [];

    // Voice (+ ducked song) bed. With SFX to layer it stops at [bed] so the
    // master fade is applied once at the very end; without SFX it emits [aout]
    // directly — byte-identical to the pre-SFX behavior (montage/fallback paths).
    $bedOut = $hasSfx ? '[bed]' : '[aout]';
    if ($songPath !== NULL) {
      $bed = sprintf('[%d:a]adelay=%d|%d,asplit=2[voice_main][voice_sc];', $voiceIdx, $introMs, $introMs)
        . sprintf('[%d:a]volume=%.3f[music];', $songIdx, self::SONG_VOLUME)
        . '[music][voice_sc]sidechaincompress=threshold=0.05:ratio=6:attack=5:release=300[duck];'
        . '[voice_main][duck]amix=inputs=2:duration=longest:dropout_transition=0';
      $bed .= $hasSfx
        ? $bedOut
        : sprintf(',afade=t=out:st=%.3f:d=%.3f[aout]', $fadeStart, self::FADE_OUT_SEC);
    }
    else {
      // No song: place the voice at the talk offset over padded silence.
      $bed = sprintf('[%d:a]adelay=%d|%d,apad%s', $voiceIdx, $introMs, $introMs, $bedOut);
    }

    $filter = $videoFilter . ';' . $bed;

    if ($hasSfx) {
      $mixLabels = ['[bed]'];
      foreach ($sfxInputs as $i => $meta) {
        $cue = $meta['cue'];
        $chain = sprintf('[%d:a]volume=%.3f', $meta['idx'], $cue['volume']);
        // Bound looped/windowed clips to their slot, resetting timestamps so the
        // subsequent adelay positions them correctly.
        if ($cue['trim'] !== NULL) {
          $chain .= sprintf(',atrim=0:%.3f,asetpts=PTS-STARTPTS', $cue['trim']);
        }
        if ($cue['fadeIn'] > 0) {
          $chain .= sprintf(',afade=t=in:st=0:d=%.3f', $cue['fadeIn']);
        }
        // Fade-out is anchored to the (known) trimmed length.
        if ($cue['fadeOut'] > 0 && $cue['trim'] !== NULL) {
          $chain .= sprintf(',afade=t=out:st=%.3f:d=%.3f', max(0.0, $cue['trim'] - $cue['fadeOut']), $cue['fadeOut']);
        }
        $chain .= sprintf(',adelay=%d:all=1[sfx%d]', $cue['delayMs'], $i);
        $filter .= ';' . $chain;
        $mixLabels[] = sprintf('[sfx%d]', $i);
      }
      // normalize=0 keeps the balanced bed at unity; SFX ride on top at their
      // own levels. One master fade closes the whole mix.
      $filter .= ';' . implode('', $mixLabels)
        . sprintf(
          'amix=inputs=%d:normalize=0:duration=longest:dropout_transition=0,afade=t=out:st=%.3f:d=%.3f[aout]',
          count($mixLabels), $fadeStart, self::FADE_OUT_SEC
        );
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
