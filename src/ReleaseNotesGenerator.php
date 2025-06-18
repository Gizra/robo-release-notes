<?php

namespace Gizra\RoboReleaseNotes;

use Robo\Contract\TaskInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Release notes generator that extracts PR and issue data from GitHub.
 */
class ReleaseNotesGenerator {
  /**
   * The Robo task context.
   *
   * @var \Robo\Contract\TaskInterface
   */
  private $task;

  /**
   * GitHub organization.
   *
   * @var string|null
   */
  private $githubOrg;

  /**
   * GitHub project/repository name.
   *
   * @var string|null
   */
  private $githubProject;

  /**
   * Constructor.
   *
   * @param \Robo\Contract\TaskInterface $task
   *   The Robo task context.
   */
  public function __construct(TaskInterface $task) {
    $this->task = $task;
  }

  /**
   * Generate release notes.
   *
   * @param string|null $tag
   *   Optional tag to compare from.
   *
   * @throws \Exception
   */
  public function generate(?string $tag = NULL): void {
    $tag = $this->resolveTagForComparison($tag);
    [$this->githubOrg, $this->githubProject] = $this->detectGitHubProject();

    if (empty($this->githubOrg) || empty($this->githubProject)) {
      throw new \Exception('GitHub project detection failed. Cannot generate release notes without GitHub API access.');
    }

    $this->validateGitHubCredentials();

    // Get commit range from git.
    $commits = $this->getCommitRange($tag);

    // Extract PR numbers from commits.
    $prNumbers = $this->extractPrNumbers($commits);

    if (empty($prNumbers)) {
      $this->say('No pull requests found in the commit range.');
      return;
    }

    // Fetch all PR and issue data from GitHub API.
    $releaseData = $this->fetchReleaseData($this->githubOrg, $this->githubProject, $prNumbers);

    // Generate and display release notes.
    $this->displayReleaseNotes($releaseData);
  }

  /**
   * Resolve which tag to compare against.
   *
   * @param string|null $tag
   *   Optional tag name.
   *
   * @return string|null
   *   The resolved tag name or null.
   *
   * @throws \Exception
   */
  private function resolveTagForComparison(?string $tag): ?string {
    $this->exec('git fetch');

    if (!empty($tag)) {
      $result = $this->taskExec("git tag | grep -x '$tag'")
        ->printOutput(FALSE)
        ->run()
        ->getMessage();

      if (empty($result)) {
        throw new \Exception("The specified tag does not exist: $tag");
      }
      return $tag;
    }

    $latestTag = trim($this->taskExec("git for-each-ref --sort=creatordate --format '%(refname:short)' refs/tags | tail -n1")
      ->printOutput(FALSE)
      ->run()
      ->getMessage());

    if (empty($latestTag)) {
      $this->say('No tags found. Generating notes for all commits.');
      return NULL;
    }

    if ($this->confirm("Compare from the latest tag: $latestTag?")) {
      return $latestTag;
    }

    throw new \Exception('No tag selected for comparison.');
  }

  /**
   * Detect GitHub organization and project from git remote.
   *
   * @return array
   *   Array containing [organization, project] or [null, null] if not found.
   */
  private function detectGitHubProject(): array {
    $remote = trim($this->taskExec("git remote get-url origin")
      ->printOutput(FALSE)
      ->run()
      ->getMessage());

    if (empty($remote)) {
      return [NULL, NULL];
    }

    // Handle both SSH and HTTPS URLs.
    $patterns = [
          // Covers git@github.com:org/repo and https://github.com/org/repo
      '/github\.com[\/:]([^\/]+)\/([^\/\.]+)/',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $remote, $matches)) {
        return [$matches[1], rtrim($matches[2], '.git')];
      }
    }

    return [NULL, NULL];
  }

  /**
   * Validate GitHub API credentials.
   *
   * @throws \Exception
   */
  private function validateGitHubCredentials(): void {
    $token = getenv('GITHUB_ACCESS_TOKEN');
    $username = getenv('GITHUB_USERNAME');

    if (empty($token) || empty($username)) {
      throw new \Exception('GitHub credentials required. Set GITHUB_ACCESS_TOKEN and GITHUB_USERNAME environment variables.');
    }
  }

  /**
   * Get commits in the specified range.
   *
   * @param string|null $tag
   *   Optional tag to compare from.
   *
   * @return array
   *   Array of commit data.
   */
  private function getCommitRange(?string $tag): array {
    $gitCommand = "git log --pretty=format:'%H¬¬%s¬¬%b'";
    if (!empty($tag)) {
      $gitCommand .= " $tag..HEAD";
    }

    $log = $this->taskExec($gitCommand)
      ->printOutput(FALSE)
      ->run()
      ->getMessage();

    if (empty($log)) {
      return [];
    }

    $commits = [];
    foreach (explode("\n", $log) as $line) {
      if (empty(trim($line))) {
        continue;
      }

      $parts = explode('¬¬', $line, 3);
      if (count($parts) >= 2) {
        $commits[] = [
          'hash' => $parts[0],
          'subject' => $parts[1],
          'body' => $parts[2] ?? '',
        ];
      }
    }

    return $commits;
  }

  /**
   * Extract PR numbers from commit messages.
   *
   * @param array $commits
   *   Array of commit data.
   *
   * @return array
   *   Array of PR numbers.
   */
  private function extractPrNumbers(array $commits): array {
    $prNumbers = [];

    foreach ($commits as $commit) {
      $text = $commit['subject'] . ' ' . $commit['body'];

      // Multiple patterns to catch different merge strategies.
      $patterns = [
            // Standard merge.
        '/Merge pull request #(\d+)/',
            // Squash and merge.
        '/\(#(\d+)\)/',
            // Any other #123 reference.
        '/#(\d+)/',
      ];

      foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
          foreach ($matches[1] as $prNumber) {
            $prNumbers[$prNumber] = TRUE;
          }
          // Stop at first match to avoid duplicates.
          break;
        }
      }
    }

    return array_keys($prNumbers);
  }

  /**
   * Fetch all release data from GitHub API.
   *
   * @param string $org
   *   GitHub organization.
   * @param string $project
   *   GitHub project name.
   * @param array $prNumbers
   *   Array of PR numbers.
   *
   * @return array
   *   Release data structure.
   */
  private function fetchReleaseData(string $org, string $project, array $prNumbers): array {
    $this->say("Fetching data for " . count($prNumbers) . " pull requests...");

    $releaseData = [
      'pull_requests' => [],
      'issues' => [],
      'contributors' => [],
      'stats' => ['additions' => 0, 'deletions' => 0, 'changed_files' => 0],
    ];

    // Fetch PR data in batches to respect rate limits.
    $prBatches = array_chunk($prNumbers, 10);

    foreach ($prBatches as $batch) {
      foreach ($batch as $prNumber) {
        try {
          $prData = $this->githubApiGet("repos/$org/$project/pulls/$prNumber");
          if (!$prData) {
            continue;
          }

          $releaseData['pull_requests'][$prNumber] = $prData;

          // Track contributors.
          if (!empty($prData->user->login)) {
            $releaseData['contributors'][$prData->user->login] =
                            ($releaseData['contributors'][$prData->user->login] ?? 0) + 1;
          }

          // Accumulate stats.
          $releaseData['stats']['additions'] += $prData->additions ?? 0;
          $releaseData['stats']['deletions'] += $prData->deletions ?? 0;
          $releaseData['stats']['changed_files'] += $prData->changed_files ?? 0;

          // Extract and fetch related issues.
          $issueNumbers = $this->extractIssueNumbers($prData);
          foreach ($issueNumbers as $issueNumber) {
            if (!isset($releaseData['issues'][$issueNumber])) {
              $issueData = $this->githubApiGet("repos/$org/$project/issues/$issueNumber");
              if ($issueData) {
                $releaseData['issues'][$issueNumber] = $issueData;

                // Track issue authors as contributors too.
                if (!empty($issueData->user->login)) {
                  $releaseData['contributors'][$issueData->user->login] =
                                        ($releaseData['contributors'][$issueData->user->login] ?? 0) + 1;
                }
              }
            }
          }
        }
        catch (\Exception $e) {
          $this->say("Warning: Failed to fetch PR #$prNumber: " . $e->getMessage());
        }
      }

      // Rate limiting: small delay between batches.
      // 0.1 second.
      usleep(100000);
    }

    return $releaseData;
  }

  /**
   * Extract issue numbers from PR data.
   *
   * @param object $prData
   *   PR data from GitHub API.
   *
   * @return array
   *   Array of issue numbers.
   */
  private function extractIssueNumbers(object $prData): array {
    $issueNumbers = [];

    // Look in PR body for issue references.
    $text = ($prData->body ?? '') . ' ' . ($prData->title ?? '');

    // Common patterns for issue references.
    $patterns = [
          // Closing keywords.
      '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#(\d+)/i',
          // Simple #123 references.
      '/#(\d+)/',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match_all($pattern, $text, $matches)) {
        $issueNumbers = array_merge($issueNumbers, $matches[1]);
      }
    }

    // Also check branch name if available (from PR head ref)
    if (!empty($prData->head->ref)) {
      if (preg_match('/(\d+)/', $prData->head->ref, $matches)) {
        $issueNumbers[] = $matches[1];
      }
    }

    return array_unique($issueNumbers);
  }

  /**
   * Display formatted release notes.
   *
   * @param array $releaseData
   *   Release data structure.
   */
  private function displayReleaseNotes(array $releaseData): void {
    $this->say('Copy release notes below');
    $this->printReleaseNotesTitle('Changelog');

    // Group PRs by issues.
    $groupedChanges = $this->groupChangesByIssue($releaseData);

    // Display issues with their PRs.
    foreach ($groupedChanges['with_issues'] as $issueNumber => $prs) {
      $issue = $releaseData['issues'][$issueNumber];
      $issueTitle = $issue->title ?? "Issue #$issueNumber";
      echo "- $issueTitle (#$issueNumber)\n";

      foreach ($prs as $prNumber) {
        $pr = $releaseData['pull_requests'][$prNumber];
        echo "  - {$pr->title} (#{$prNumber})\n";
      }
    }

    // Display PRs without associated issues.
    if (!empty($groupedChanges['without_issues'])) {
      echo "\n### Other Changes\n";
      foreach ($groupedChanges['without_issues'] as $prNumber) {
        $pr = $releaseData['pull_requests'][$prNumber];
        echo "- {$pr->title} (#{$prNumber})\n";
      }
    }

    // Display contributors.
    if (!empty($releaseData['contributors'])) {
      $this->printReleaseNotesTitle('Contributors');
      arsort($releaseData['contributors']);
      foreach ($releaseData['contributors'] as $username => $count) {
        echo "- @$username ($count)\n";
      }
    }

    // Display statistics.
    $stats = $releaseData['stats'];
    $this->printReleaseNotesSection('Code Statistics', [
      "Lines added: {$stats['additions']}",
      "Lines deleted: {$stats['deletions']}",
      "Files changed: {$stats['changed_files']}",
    ]);
  }

  /**
   * Group pull requests by their associated issues.
   *
   * @param array $releaseData
   *   Release data structure.
   *
   * @return array
   *   Grouped changes structure.
   */
  private function groupChangesByIssue(array $releaseData): array {
    $grouped = [
      'with_issues' => [],
      'without_issues' => [],
    ];

    foreach ($releaseData['pull_requests'] as $prNumber => $prData) {
      $issueNumbers = $this->extractIssueNumbers($prData);

      if (empty($issueNumbers)) {
        $grouped['without_issues'][] = $prNumber;
      }
      else {
        // Associate with the first issue found.
        $issueNumber = $issueNumbers[0];
        if (!isset($grouped['with_issues'][$issueNumber])) {
          $grouped['with_issues'][$issueNumber] = [];
        }
        $grouped['with_issues'][$issueNumber][] = $prNumber;
      }
    }

    return $grouped;
  }

  /**
   * Print a title for the release notes.
   *
   * @param string $title
   *   Section title.
   */
  private function printReleaseNotesTitle(string $title): void {
    echo "\n\n## $title\n";
  }

  /**
   * Print a section for the release notes.
   *
   * @param string $title
   *   Section title.
   * @param array $lines
   *   Bullet points.
   * @param bool $printKey
   *   Whether to print the key of the array.
   */
  private function printReleaseNotesSection(string $title, array $lines, bool $printKey = FALSE): void {
    if (!empty($title)) {
      $this->printReleaseNotesTitle($title);
    }
    foreach ($lines as $key => $line) {
      if ($printKey) {
        print "- $key ($line)\n";
      }
      elseif (substr($line, 0, 1) == '-') {
        print "$line\n";
      }
      else {
        print "- $line\n";
      }
    }
  }

  /**
   * Enhanced GitHub API GET with better error handling.
   *
   * @param string $path
   *   API path to request.
   *
   * @return object|null
   *   Decoded JSON response or null if not found.
   *
   * @throws \Exception
   */
  private function githubApiGet(string $path) {
    $token = getenv('GITHUB_ACCESS_TOKEN');
    $username = getenv('GITHUB_USERNAME');

    $ch = curl_init('https://api.github.com/' . $path);
    curl_setopt_array($ch, [
      CURLOPT_USERAGENT => 'Gizra Robo Release Notes Generator',
      CURLOPT_USERPWD => "$username:$token",
      CURLOPT_TIMEOUT => 30,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/vnd.github.v3+json',
      ],
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === Response::HTTP_NOT_FOUND) {
      // Resource not found - this might be expected,
      // e.g., PR was actually an issue.
      return NULL;
    }

    if ($httpCode !== Response::HTTP_OK) {
      $errorDetail = $result ? json_decode($result) : 'Unknown error';
      throw new \Exception("GitHub API request failed (HTTP $httpCode): " . print_r($errorDetail, TRUE));
    }

    return json_decode($result);
  }

  /**
   * Helper method to say something via the task context.
   *
   * @param string $text
   *   Text to output.
   */
  private function say(string $text): void {
    if (method_exists($this->task, 'say')) {
      $this->task->say($text);
    }
    else {
      echo $text . "\n";
    }
  }

  /**
   * Helper method to ask for confirmation via the task context.
   *
   * @param string $question
   *   Question to ask.
   *
   * @return bool
   *   User's response.
   */
  private function confirm(string $question): bool {
    if (method_exists($this->task, 'confirm')) {
      return $this->task->confirm($question);
    }

    // Fallback for non-interactive environments.
    return TRUE;
  }

  /**
   * Helper method to execute commands via the task context.
   *
   * @param string $command
   *   Command to execute.
   *
   * @return mixed
   *   Task execution result or exec() return value.
   */
  private function exec(string $command) {
    if (method_exists($this->task, '_exec')) {
      return $this->task->_exec($command);
    }

    return exec($command);
  }

  /**
   * Helper method to create task exec via the task context.
   *
   * @param string $command
   *   Command to execute.
   *
   * @return mixed
   *   Task exec instance.
   */
  private function taskExec(string $command) {
    if (method_exists($this->task, 'taskExec')) {
      return $this->task->taskExec($command);
    }

    // Simple fallback implementation.
    return new class($command) {

      /**
       * The command to execute.
       *
       * @var string
       */
      private $command;

      /**
       * Whether to print output.
       *
       * @var bool
       */
      private $output = TRUE;

      /**
       * Constructor.
       *
       * @param string $command
       *   The command to execute.
       */
      public function __construct($command) {
        $this->command = $command;
      }

      /**
       * Set whether to print output.
       *
       * @param bool $output
       *   Whether to print output.
       *
       * @return $this
       *   Returns self for method chaining.
       */
      public function printOutput($output) {
        $this->output = $output;
        return $this;
      }

      /**
       * Execute the command.
       *
       * @return object
       *   Object containing the execution result.
       */
      public function run() {
        $result = shell_exec($this->command);
        return new class($result) {

          /**
           * The result message.
           *
           * @var string
           */
          private $message;

          /**
           * Constructor.
           *
           * @param string $message
           *   The message from command execution.
           */
          public function __construct($message) {
            $this->message = $message;
          }

          /**
           * Get the message from command execution.
           *
           * @return string
           *   The execution message.
           */
          public function getMessage() {
            return $this->message;
          }

        };
      }

    };
  }

}
