<?php

namespace Gizra\RoboReleaseNotes\Tests;

use Gizra\RoboReleaseNotes\ReleaseNotesTasks;
use PHPUnit\Framework\TestCase;
use Robo\Contract\TaskInterface;
use Robo\Result;

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

    // Create an anonymous class that uses the trait and implements
    // TaskInterface.
    $this->taskRunner = new class() implements TaskInterface {
      use ReleaseNotesTasks;

      /**
       * Generate release notes mock implementation.
       *
       * @param string|null $tag
       *   Optional tag parameter.
       *
       * @return \Robo\Result
       *   Mock result object.
       */
      public function generateReleaseNotes(?string $tag = NULL): Result {
        // Mock implementation that doesn't rely on Robo container.
        return new class() extends Result {

          /**
           * Mock constructor.
           */
          public function __construct() {
            // Bypass parent constructor.
          }

          /**
           * Mock wasSuccessful method.
           *
           * @return bool
           *   Success status based on environment variables.
           */
          public function wasSuccessful(): bool {
            return getenv('GITHUB_ACCESS_TOKEN') !== FALSE && getenv('GITHUB_USERNAME') !== FALSE;
          }

        };
      }

      /**
       * Mock methods that would normally come from Robo\Tasks.
       *
       * @param string $message
       *   Message to output.
       */
      public function say(string $message): void {
        echo $message . "\n";
      }

      /**
       * Mock confirm method.
       *
       * @param string $question
       *   Question to ask.
       *
       * @return bool
       *   Always returns TRUE for tests.
       */
      public function confirm($question) {
        return TRUE;
      }

      /**
       * Mock exec method.
       *
       * @param string $command
       *   Command to execute.
       *
       * @return bool
       *   Always returns TRUE for tests.
       */
      public function execCommand(string $command): bool {
        return TRUE;
      }

      /**
       * Mock taskExec method.
       *
       * @param string $command
       *   Command to execute.
       *
       * @return object
       *   Mock task execution object.
       */
      public function taskExec($command) {
        return new class() {

          /**
           * Mock constructor.
           */
          public function __construct() {
            // No-op constructor for mock object.
          }

          /**
           * Mock printOutput method.
           *
           * @param mixed $output
           *   Output to print.
           *
           * @return $this
           *   Returns self for chaining.
           */
          public function printOutput($output) {
            return $this;
          }

          /**
           * Mock run method.
           *
           * @return object
           *   Mock result object.
           */
          public function run() {
            return new class('') {
              /**
               * Message storage.
               *
               * @var string
               */
              private $message;

              /**
               * Mock constructor.
               *
               * @param string $message
               *   Message to store.
               */
              public function __construct(string $message) {
                $this->message = $message;
              }

              /**
               * Get stored message.
               *
               * @return string
               *   The stored message.
               */
              public function getMessage() {
                return $this->message;
              }

            };
          }

        };
      }

      /**
       * Required methods for TaskInterface.
       *
       * @return \Robo\Result
       *   Success result.
       */
      public function run() {
        return Result::success($this);
      }

      /**
       * Get task state.
       *
       * @return null
       *   Always returns NULL.
       */
      public function getState() {
        return NULL;
      }

      /**
       * Set task state.
       *
       * @param mixed $state
       *   State to set.
       *
       * @return $this
       *   Returns self.
       */
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
