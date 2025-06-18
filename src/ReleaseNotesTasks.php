<?php

namespace Gizra\RoboReleaseNotes;

use Robo\Collection\Tasks;
use Robo\Result;

/**
 * Robo tasks for generating release notes from GitHub PRs and issues.
 */
trait ReleaseNotesTasks {
  use Tasks;

  /**
   * Generate release notes from GitHub PRs and issues.
   *
   * @param string|null $tag
   *   Optional tag to compare from. If not provided, uses latest tag.
   *
   * @return \Robo\Result
   *   Result object indicating success or failure of release notes generation.
   */
  public function generateReleaseNotes(?string $tag = NULL): Result {
    try {
      $generator = new ReleaseNotesGenerator($this);
      $generator->generate($tag);
      return Result::success($this);
    }
    catch (\Exception $e) {
      return Result::error($this, $e->getMessage());
    }
  }

}
