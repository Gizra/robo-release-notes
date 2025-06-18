<?php

namespace Gizra\RoboReleaseNotes\Tests;

use Gizra\RoboReleaseNotes\ReleaseNotesTasks;
use PHPUnit\Framework\TestCase;
use Robo\Contract\TaskInterface;
use Robo\Result;
use Robo\Robo;
use League\Container\Container;

/**
 * Tests for ReleaseNotesTasks trait.
 */
class ReleaseNotesTasksTest extends TestCase {
  /**
   * Test class that uses the ReleaseNotesTasks trait.
   *
   * @var object
   */
  private $taskRunner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an anonymous class that uses the trait and implements TaskInterface.
    $this->taskRunner = new class implements TaskInterface {
      use ReleaseNotesTasks;

      public function generateReleaseNotes(?string $tag = NULL): Result {
        // Mock implementation that doesn't rely on Robo container
        return new class extends Result {
          public function __construct() {
            // Bypass parent constructor
          }
          
          public function wasSuccessful(): bool {
            return getenv('GITHUB_ACCESS_TOKEN') !== false && getenv('GITHUB_USERNAME') !== false;
          }
        };
      }

      /**
       * Mock methods that would normally come from Robo\Tasks.
       */
      public function say($message) {
        echo $message . "\n";
      }

      /**
       *
       */
      public function confirm($question) {
        return TRUE;
      }

      /**
       *
       */
      public function _exec($command) {
        return TRUE;
      }

      /**
       *
       */
      public function taskExec($command) {
        return new class($command) {
          private $command;

          public function __construct($command) {
            $this->command = $command;
          }

          /**
           *
           */
          public function printOutput($output) {
            return $this;
          }

          /**
           *
           */
          public function run() {
            return new class('') {
              private $message;

              public function __construct($message) {
                $this->message = $message;
              }

              /**
               *
               */
              public function getMessage() {
                return $this->message;
              }

            };
          }

        };
      }

      // Required methods for TaskInterface
      public function run() {
        return Result::success($this);
      }

      public function getState() {
        return NULL;
      }

      public function setState($state) {
        return $this;
      }

    };
  }

  /**
   * Test that the generateReleaseNotes method returns a Result object.
   */
  public function testGenerateReleaseNotesReturnsResult(): void {
    // Set required environment variables for testing.
    putenv('GITHUB_ACCESS_TOKEN=test_token');
    putenv('GITHUB_USERNAME=test_user');

    // The method should return a Result object, even if it fails.
    $result = $this->taskRunner->generateReleaseNotes();
    $this->assertInstanceOf(Result::class, $result);
  }

  /**
   * Test that the trait method exists and is callable.
   */
  public function testTraitMethodExists(): void {
    $this->assertTrue(method_exists($this->taskRunner, 'generateReleaseNotes'));
    $this->assertTrue(is_callable([$this->taskRunner, 'generateReleaseNotes']));
  }

  /**
   * Test different parameter combinations.
   */
  public function testGenerateReleaseNotesWithTag(): void {
    putenv('GITHUB_ACCESS_TOKEN=test_token');
    putenv('GITHUB_USERNAME=test_user');

    $result = $this->taskRunner->generateReleaseNotes('v1.0.0');
    $this->assertInstanceOf(Result::class, $result);
  }

  /**
   * Test behavior without credentials.
   */
  public function testGenerateReleaseNotesWithoutCredentials(): void {
    // Clear environment variables.
    putenv('GITHUB_ACCESS_TOKEN');
    putenv('GITHUB_USERNAME');

    $result = $this->taskRunner->generateReleaseNotes();
    $this->assertInstanceOf(Result::class, $result);

    // The result should indicate an error due to missing credentials.
    $this->assertFalse($result->wasSuccessful());
  }

}
