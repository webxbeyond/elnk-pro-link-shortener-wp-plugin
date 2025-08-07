# WordPress.org Plugin Deployment Setup

This guide explains how to set up automatic deployment to WordPress.org plugin repository using GitHub Actions.

## Prerequisites

1. **WordPress.org Plugin Repository Access**
   - Your plugin must be approved and available on WordPress.org
   - You need SVN access credentials for your plugin

2. **GitHub Repository Secrets**
   Set up the following secrets in your GitHub repository (Settings > Secrets and variables > Actions):
   
   ```
   SVN_USERNAME: your-wordpress-org-username
   SVN_PASSWORD: your-wordpress-org-password
   ```

## Setup Steps

### 1. Initial WordPress.org Submission

Before using the automated deployment, you need to:

1. Submit your plugin to WordPress.org manually for initial review
2. Wait for approval (this can take several weeks)
3. Get access to the SVN repository for your plugin

### 2. GitHub Repository Setup

1. Ensure your plugin follows WordPress.org guidelines:
   - `README.txt` file with proper formatting
   - Main plugin file with proper headers
   - Proper versioning

2. Create tags for releases:
   ```bash
   git tag 1.0.0
   git push origin 1.0.0
   ```

### 3. Version Management

Ensure version consistency across:
- Main plugin file (`elnk-pro-shortener.php`) - Version header
- `README.txt` - Stable tag field
- Git tags

Example:
```php
// In elnk-pro-shortener.php
/**
 * Version: 1.0.1
 */

// In README.txt
Stable tag: 1.0.1

// Git tag
git tag 1.0.1
```

## Deployment Workflow

The GitHub Action will:

1. **Validate** plugin structure and version consistency
2. **Build** the plugin (if build scripts exist)
3. **Package** the plugin for deployment
4. **Deploy** to WordPress.org SVN repository
5. **Create** a GitHub release with downloadable ZIP

## Manual Deployment

You can also trigger deployment manually:

1. Go to Actions tab in your GitHub repository
2. Select "Deploy to WordPress.org" workflow
3. Click "Run workflow"
4. Enter the tag version to deploy

## Troubleshooting

### Common Issues:

1. **Version Mismatch**: Ensure all versions match exactly
2. **SVN Credentials**: Verify your WordPress.org credentials are correct
3. **Plugin Approval**: Make sure your plugin is approved on WordPress.org
4. **File Structure**: Ensure all required files are present

### Debug Steps:

1. Check the Actions tab for detailed logs
2. Verify your secrets are set correctly
3. Test SVN access manually if needed

## WordPress.org Guidelines

Ensure your plugin meets all WordPress.org requirements:

- GPL-compatible license
- No premium/paid features promotion
- Proper security practices
- Clean, readable code
- Proper documentation

## Resources

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [WordPress.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [10up Deploy Action Documentation](https://github.com/10up/action-wordpress-plugin-deploy)
