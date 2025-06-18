<?php

namespace Gizra\RoboReleaseNotes\Tests;

use Robo\Result;
use Robo\Contract\TaskInterface;
use Gizra\RoboReleaseNotes\ReleaseNotesGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ReleaseNotesGenerator class.
 */
class ReleaseNotesGeneratorTest extends TestCase {
  /**
   * Mock task context.
   *
   * @var object
   */
  private $mockTask;

  /**
   * Release notes generator instance.
   *
   * @var \Gizra\RoboReleaseNotes\ReleaseNotesGenerator
   */
  private $generator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a mock task object with required methods.
    $this->mockTask = new class() implements TaskInterface {
      /**
       * Callback function for taskExec method.
       *
       * @var callable|null
       */
      public $taskExecCallback = NULL;

      /**
       * Mock taskExec method.
       *
       * @param string $command
       *   The command to execute.
       *
       * @return object
       *   Mock task execution result.
       */
      public function taskExec(string $command): object {
        if ($this->taskExecCallback) {
          return call_user_func($this->taskExecCallback, $command);
        }
        return $this->createDefaultTaskExecResult();
      }

      /**
       * Mock say method.
       *
       * @param string $message
       *   The message to output.
       */
      public function say(string $message): void {
        // Mock method.
      }

      /**
       * Mock confirm method.
       *
       * @param string $question
       *   The question to ask.
       *
       * @return bool
       *   Always returns TRUE for tests.
       */
      public function confirm($question) {
        return TRUE;
      }

      /**
       * Mock exec command method.
       *
       * @param string $command
       *   The command to execute.
       *
       * @return null
       *   Always returns NULL for tests.
       */
      public function execCommand($command) {
        return NULL;
      }

      /**
       * Required TaskInterface methods.
       */
      public function run(): ?Result {
        return NULL;
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
       *   The state to set.
       *
       * @return $this
       *   Returns self for chaining.
       */
      public function setState($state) {
        return $this;
      }

      /**
       * Create default task execution result.
       *
       * @return object
       *   Mock task execution result object.
       */
      private function createDefaultTaskExecResult() {
        return new class('') {
          /**
           * Message storage.
           *
           * @var string
           */
          private $message;

          public function __construct(string $message) {
            $this->message = $message;
          }

          /**
           * Mock printOutput method.
           *
           * @param mixed $output
           *   The output to print.
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
           * @return $this
           *   Returns self for chaining.
           */
          public function run() {
            return $this;
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
    $this->generator = new ReleaseNotesGenerator($this->mockTask);
  }

  /**
   * Test PR number extraction from different commit message formats.
   */
  public function testExtractPrNumbers(): void {
    $method = $this->getPrivateMethod('extractPrNumbers');

    // Test standard merge commit.
    $commits = [
          [
            'hash' => 'abc123',
            'subject' => 'Merge pull request #123 from feature/branch',
            'body' => 'Add new feature',
          ],
    ];
    $result = $method->invokeArgs($this->generator, [$commits]);
    $this->assertEquals([123], $result);

    // Test squash and merge commit.
    $commits = [
          [
            'hash' => 'def456',
            'subject' => 'Add new feature (#124)',
            'body' => '',
          ],
    ];
    $result = $method->invokeArgs($this->generator, [$commits]);
    $this->assertEquals([124], $result);

    // Test multiple PRs in one commit.
    $commits = [
          [
            'hash' => 'ghi789',
            'subject' => 'Fix issue #125 and #126',
            'body' => 'Related to #127',
          ],
    ];
    $result = $method->invokeArgs($this->generator, [$commits]);
    // Should extract all PR references from the commit.
    $this->assertEquals([125, 126, 127], $result);

    // Test no PR numbers.
    $commits = [
          [
            'hash' => 'jkl012',
            'subject' => 'Regular commit message',
            'body' => 'No PR references here',
          ],
    ];
    $result = $method->invokeArgs($this->generator, [$commits]);
    $this->assertEquals([], $result);
  }

  /**
   * Test issue number extraction from PR data.
   */
  public function testExtractIssueNumbers(): void {
    $method = $this->getPrivateMethod('extractIssueNumbers');

    // Test closing keywords.
    $prData = (object) [
      'title' => 'Fix user authentication',
      'body' => 'This PR fixes #456 and closes #457',
      'head' => (object) ['ref' => 'feature/458-auth-fix'],
    ];
    $result = $method->invokeArgs($this->generator, [$prData]);
    $this->assertContains('456', $result);
    $this->assertContains('457', $result);
    $this->assertContains('458', $result);

    // Test simple references.
    $prData = (object) [
      'title' => 'Update documentation (#459)',
      'body' => 'Related to #460',
      'head' => (object) ['ref' => 'docs-update'],
    ];
    $result = $method->invokeArgs($this->generator, [$prData]);
    $this->assertContains('459', $result);
    $this->assertContains('460', $result);

    // Test no issue references.
    $prData = (object) [
      'title' => 'Regular PR title',
      'body' => 'No issue references',
      'head' => (object) ['ref' => 'feature-branch'],
    ];
    $result = $method->invokeArgs($this->generator, [$prData]);
    $this->assertEquals([], $result);
  }

  /**
   * Test GitHub project detection from various remote URL formats.
   */
  public function testDetectGitHubProject(): void {
    $method = $this->getPrivateMethod('detectGitHubProject');

    // Mock taskExec to return different remote URLs.
    $this->mockTask->taskExecCallback = function ($command) {
      if (strpos($command, 'git remote get-url origin') !== FALSE) {
        $mockExecResult = new class('git@github.com:Gizra/test-repo.git') {
          /**
           * Message storage.
           *
           * @var string
           */
          private $message;

          public function __construct(string $message) {
            $this->message = $message;
          }

          /**
           * Mock printOutput method.
           *
           * @param mixed $output
           *   The output to print.
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
           * @return $this
           *   Returns self for chaining.
           */
          public function run() {
            return $this;
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
        return $mockExecResult;
      }
    };

    // Test SSH URL format.
    $result = $method->invokeArgs($this->generator, []);
    $this->assertEquals(['Gizra', 'test-repo'], $result);
  }

  /**
   * Test changes grouping by issues.
   */
  public function testGroupChangesByIssue(): void {
    $method = $this->getPrivateMethod('groupChangesByIssue');

    $releaseData = [
      'pull_requests' => [
        '123' => (object) [
          'title' => 'Fix authentication bug',
          'body' => 'Fixes #456',
          'head' => (object) ['ref' => 'fix-auth'],
        ],
        '124' => (object) [
          'title' => 'Add new feature',
          'body' => 'Implements #457',
          'head' => (object) ['ref' => 'feature-branch'],
        ],
        '125' => (object) [
          'title' => 'Update documentation',
          'body' => 'No issue reference',
          'head' => (object) ['ref' => 'docs'],
        ],
      ],
      'issues' => [
        '456' => (object) ['title' => 'Authentication fails'],
        '457' => (object) ['title' => 'Need new feature'],
      ],
    ];

    $result = $method->invokeArgs($this->generator, [$releaseData]);

    $this->assertArrayHasKey('with_issues', $result);
    $this->assertArrayHasKey('without_issues', $result);
    $this->assertArrayHasKey('456', $result['with_issues']);
    $this->assertContains(123, $result['with_issues']['456']);
    $this->assertContains(125, $result['without_issues']);
  }

  /**
   * Test GitHub credentials validation.
   */
  public function testValidateGitHubCredentials(): void {
    $method = $this->getPrivateMethod('validateGitHubCredentials');

    // Test missing credentials.
    putenv('GITHUB_ACCESS_TOKEN=');
    putenv('GITHUB_USERNAME=');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('GitHub credentials required');
    $method->invokeArgs($this->generator, []);
  }

  /**
   * Test commit range parsing.
   */
  public function testGetCommitRange(): void {
    $method = $this->getPrivateMethod('getCommitRange');

    // Mock taskExec to return git log output.
    $this->mockTask->taskExecCallback = function ($command) {
      if (strpos($command, 'git log --pretty=format') !== FALSE) {
        $mockExecResult = new class("abc123¬¬Fix bug¬¬Detailed description\ndef456¬¬Add feature¬¬Another description") {
          /**
           * Message storage.
           *
           * @var string
           */
          private $message;

          public function __construct(string $message) {
            $this->message = $message;
          }

          /**
           * Mock printOutput method.
           *
           * @param mixed $output
           *   The output to print.
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
           * @return $this
           *   Returns self for chaining.
           */
          public function run() {
            return $this;
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
        return $mockExecResult;
      }
    };

    $result = $method->invokeArgs($this->generator, [NULL]);

    $this->assertCount(2, $result);
    $this->assertEquals('abc123', $result[0]['hash']);
    $this->assertEquals('Fix bug', $result[0]['subject']);
    $this->assertEquals('Detailed description', $result[0]['body']);
    $this->assertEquals('def456', $result[1]['hash']);
    $this->assertEquals('Add feature', $result[1]['subject']);
    $this->assertEquals('Another description', $result[1]['body']);
  }

  /**
   * Get a private method for testing.
   *
   * @param string $methodName
   *   The method name to get.
   *
   * @return \ReflectionMethod
   *   The reflection method.
   */
  private function getPrivateMethod(string $methodName): \ReflectionMethod {
    $reflection = new \ReflectionClass($this->generator);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

}
