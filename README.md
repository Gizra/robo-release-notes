# Robo Release Notes

[![Build Status](https://travis-ci.org/Gizra/robo-release-notes.svg?branch=master)](https://travis-ci.org/Gizra/robo-release-notes)
[![Latest Stable Version](https://poser.pugx.org/gizra/robo-release-notes/v/stable)](https://packagist.org/packages/gizra/robo-release-notes)
[![Total Downloads](https://poser.pugx.org/gizra/robo-release-notes/downloads)](https://packagist.org/packages/gizra/robo-release-notes)
[![License](https://poser.pugx.org/gizra/robo-release-notes/license)](https://packagist.org/packages/gizra/robo-release-notes)

A Robo task for generating comprehensive release notes from GitHub PRs and issues.

## Features

- **Automatic PR Detection**: Extracts pull request numbers from git commit messages using multiple merge strategies
- **GitHub API Integration**: Fetches detailed information about PRs and their associated issues
- **Smart Grouping**: Groups pull requests by their related issues for better organization
- **Contributor Tracking**: Automatically tracks contributors from both PR authors and issue reporters
- **Code Statistics**: Provides statistics on lines changed and files modified
- **Flexible Tag Comparison**: Compare from any tag or automatically use the latest tag
- **Rate Limiting**: Respects GitHub API rate limits with intelligent batching

## Installation

Install via Composer:

```bash
composer require gizra/robo-release-notes
```

## Usage

### Basic Setup

1. **Include the trait in your RoboFile.php**:

```php
<?php

use Gizra\RoboReleaseNotes\ReleaseNotesTasks;

class RoboFile extends \Robo\Tasks
{
    use ReleaseNotesTasks;
}
```

2. **Set up environment variables**:

```bash
export GITHUB_ACCESS_TOKEN="your_github_token"
export GITHUB_USERNAME="your_github_username"
```

### Command Usage

Generate release notes from the latest tag:

```bash
robo generate:release-notes
```

Generate release notes from a specific tag:

```bash
robo generate:release-notes v1.2.0
```

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `GITHUB_ACCESS_TOKEN` | GitHub personal access token with repo access | Yes |
| `GITHUB_USERNAME` | Your GitHub username | Yes |

To create a GitHub personal access token:
1. Go to GitHub Settings → Developer settings → Personal access tokens
2. Generate a new token with `repo` scope
3. Copy the token and set it as an environment variable

### Sample Output

```markdown
## Changelog

- Implement user authentication system (#123)
  - Add login functionality (#145)
  - Add password reset feature (#146)
  - Implement session management (#147)

- Fix payment processing bugs (#124)
  - Resolve credit card validation issues (#148)

### Other Changes

- Update documentation (#149)
- Refactor utility functions (#150)

## Contributors

- @alice (5)
- @bob (3)
- @charlie (2)

## Code Statistics

- Lines added: 1,234
- Lines deleted: 567
- Files changed: 23
```

## How It Works

1. **Git Analysis**: Scans git commits between the specified tag and HEAD
2. **PR Extraction**: Uses regex patterns to extract PR numbers from commit messages
3. **GitHub API Calls**: Fetches detailed PR and issue data from GitHub
4. **Issue Linking**: Automatically links PRs to their associated issues using:
   - Closing keywords (fixes, closes, resolves)
   - Issue references in PR titles and descriptions
   - Issue numbers in branch names
5. **Data Aggregation**: Combines all data into a structured release notes format

## Testing

Run the test suite:

```bash
composer test
```

Run code style checks:

```bash
composer cs
```

Fix code style issues:

```bash
composer cbf
```

## Requirements

- PHP 7.4 or higher
- Robo 3.0 or 4.0
- cURL extension
- JSON extension
- Git repository with GitHub remote
- GitHub API access

## Advanced Configuration

### Custom GitHub API Endpoint

For GitHub Enterprise users, you can extend the `ReleaseNotesGenerator` class and override the `githubApiGet` method to use a custom API endpoint.

### Custom Output Format

You can extend the `ReleaseNotesGenerator` class and override the `displayReleaseNotes` method to customize the output format.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- [Issues](https://github.com/Gizra/robo-release-notes/issues)
- [Documentation](https://github.com/Gizra/robo-release-notes/wiki)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a complete list of changes.