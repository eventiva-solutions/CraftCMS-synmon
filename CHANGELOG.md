# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 1.0.0 - 2026-03-12

### Added
- Initial release of SynMon — Synthetic Monitoring for Craft CMS
- Step editor with support for Navigate, Click, Fill, Select, Hover, Scroll, Wait, PressKey, AssertVisible, AssertNotVisible, AssertText, AssertCount, AssertUrl, AssertTitle, WaitForSelector
- Live test view with real-time browser screenshots per step
- Network console tab showing XHR/Fetch requests with method and HTTP status
- Visual cron builder for scheduling test suites (weekdays, month days, times)
- Queue-based background run via Craft Queue jobs
- Console command `synmon/run` to trigger scheduled suites from server cron
- Run history with step-by-step logs and per-step screenshots
- Suite management: clone, JSON export and import
- Email and webhook (Slack etc.) failure/success notifications
- Auto-setup of Playwright and Chromium on first test run
- Dashboard with suite overview and enable/disable toggle
- Selector hints and assertText Shadow DOM support
- Full i18n support with English as default language
- Path hint for Node.js binary in settings
- Automatic DB migration on first request (no manual `craft migrate` needed)