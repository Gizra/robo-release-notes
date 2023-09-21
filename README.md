This PHP trait is designed to generate release notes for your GitHub projects using Git history. It retrieves Git commits since the last tag, extracts relevant details, and optionally fetches additional metadata from GitHub to produce a comprehensive changelog.

## Requirements

- Robo 4.x
- PHP 7.x or higher
- `git` command-line tool installed
- A GitHub repository with at least one tag
- GitHub Personal Access Token set in the environment variable `GITHUB_ACCESS_TOKEN`
- GitHub Username set in the environment variable `GITHUB_USERNAME`

## Usage

1. `composer require gizra/robo-release-notes`
1. Import the `ReleaseNotesTasks` trait into your RoboFile class.
1. `robo` to see the new commands
