# Changelog

## Notes

- GitHub: https://github.com/mr-manuel/LoxBerry-Plugin-miniserverbackup-ng
- Documentation: https://wiki.loxberry.de/plugins/miniserverbackup-ng/start

## 2025.03.10

- Added: Option to select backup compression or also no compression (for deduplication)
- Changed: Exclude `/sys/tokens.xml` from backup, since the permission is denied with LoxConfig `15.5.x.x`
- Changed: Fixed settings template layout and saving of logging level change
- Changed: Plugin name from `miniserverbackup` to `miniserverbackup-ng` (next generation) to avoid conflicts with the old plugin

Forked from: https://github.com/Woersty/LoxBerry-Plugin-miniserverbackup
