<?php

namespace Gizra\RoboReleaseNotes;

use Robo\Result;
use Exception;

/**
 * Robo tasks for generating release notes from GitHub PRs and issues.
 */
trait ReleaseNotesTasks {

    /**
     * Generate release notes from GitHub PRs and issues.
     *
     * @param string|null $tag
     *   Optional tag to compare from. If not provided, uses latest tag.
     *
     * @return \Robo\Result
     */
    public function generateReleaseNotes(?string $tag = null): Result
    {
        try {
            $generator = new ReleaseNotesGenerator($this);
            $generator->generate($tag);
            return Result::success($this);
        } catch (Exception $e) {
            return Result::error($this, $e->getMessage());
        }
    }
}
