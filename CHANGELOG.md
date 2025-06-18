# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Robo Release Notes package
- `ReleaseNotesTasks` trait for Robo integration
- `ReleaseNotesGenerator` class for core functionality
- Support for multiple merge strategies (standard merge, squash merge, rebase)
- GitHub API integration for fetching PR and issue data
- Automatic issue linking from PR descriptions and branch names
- Contributor tracking and statistics
- Comprehensive test coverage
- Travis CI integration
- PSR-12 code style compliance

### Features
- Generate release notes from git history
- Group PRs by associated issues
- Extract PR numbers from various commit message formats
- Fetch detailed GitHub API data for PRs and issues
- Track code statistics (lines added/deleted, files changed)
- Flexible tag comparison (specific tag or latest tag)
- Rate limiting for GitHub API requests
- Robust error handling and validation

## [1.0.0] - 2024-06-18

### Added
- Initial stable release