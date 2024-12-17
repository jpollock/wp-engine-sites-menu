# WP Engine Sites Menu

A WordPress plugin that adds a convenient dropdown menu to your admin bar for quick access to all your WP Engine sites and environments.

## Description

This plugin integrates with the WP Engine API to provide easy navigation between your WP Engine sites directly from the WordPress admin bar. It's particularly useful for developers and agencies managing multiple WP Engine installations.

### Key Features

- **Quick Access Menu**: Adds a dropdown menu to the WordPress admin bar for fast navigation between sites
- **Current Site Display**: Clearly shows your current site and its related environments (Production, Staging, Development)
- **Search Functionality**: Instantly search through all your WP Engine sites and installations
- **Smart Caching**: Caches API responses to minimize API calls and improve performance
- **Secure Storage**: Encrypts API credentials using WordPress salts for secure storage
- **Environment Labels**: Clearly distinguishes between production, staging, and development environments

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WP Engine hosting account
- WP Engine API credentials

## Installation

1. Download the plugin zip file from the [latest release](https://github.com/yourusername/wp-engine-sites-menu/releases/latest)
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Click "Install Now"
5. After installation, click "Activate"

### Manual Installation

1. Upload the `wp-engine-sites-menu` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

1. Go to Settings > WP Engine API in your WordPress admin
2. Enter your WP Engine username and password
3. (Optional) Adjust the cache duration for API responses
4. Click "Save Settings"
5. Use the "Test Credentials" button to verify your API connection

## Usage

Once configured, you'll see a "WP Engine Sites" menu in your WordPress admin bar. This menu provides:

1. **Current Site Section**:
   - Shows your current site name
   - Lists all environments (Production/Staging/Development) for quick access

2. **Search Function**:
   - Use the search box to filter through all your WP Engine installations
   - Search by site name, install name, or domain

3. **Other Sites**:
   - Lists all other WP Engine sites you have access to
   - Includes environment indicators for each installation

## Security

The plugin implements several security measures:

- API credentials are encrypted using WordPress salts
- All API requests are made server-side
- Nonce verification for AJAX requests
- Capability checking for admin functions

## Caching

The plugin caches WP Engine API responses to improve performance:

- Default cache duration: 1 hour (3600 seconds)
- Configurable through the settings page
- Cache is automatically cleared when testing credentials
- Reduces API calls and improves menu load time

## Releases

This plugin follows [Semantic Versioning](https://semver.org/):

- MAJOR version for incompatible API changes
- MINOR version for added functionality in a backward compatible manner
- PATCH version for backward compatible bug fixes

### Creating a New Release

1. Update version numbers in:
   - `wp-engine-sites-menu.php` (Plugin header)
   - JavaScript files where version is specified
   - Any other files referencing the version number

2. Update the CHANGELOG.md file:
   - Add a new version section
   - Document all notable changes under appropriate categories:
     - Added
     - Changed
     - Deprecated
     - Removed
     - Fixed
     - Security

3. Create and push a new version tag:
   ```bash
   git add .
   git commit -m "Prepare for version X.Y.Z"
   git tag -a vX.Y.Z -m "Version X.Y.Z"
   git push origin main --tags
   ```

4. The GitHub Actions workflow will automatically:
   - Create a clean distribution package
   - Remove development files
   - Create a zip file
   - Create a GitHub release
   - Attach the zip file
   - Add release notes from CHANGELOG.md

## Support

For bug reports or feature requests, please use the [GitHub issues page](https://github.com/yourusername/wp-engine-sites-menu/issues).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Screenshots

*(Coming soon)*

1. WP Engine Sites dropdown menu in action
2. Plugin settings page
3. Search functionality demonstration
