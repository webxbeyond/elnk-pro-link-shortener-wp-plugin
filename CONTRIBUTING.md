# Contributing to elnk.pro Link Shortener Plugin

Thank you for your interest in contributing to the elnk.pro Link Shortener Plugin! We welcome contributions from everyone.

## 🎯 How to Contribute

### Reporting Issues
- **Bug Reports**: Use the GitHub Issues tab to report bugs
- **Feature Requests**: Suggest new features or improvements
- **Documentation**: Help improve our documentation

### Code Contributions
1. **Fork** the repository
2. **Create a branch** for your feature/fix: `git checkout -b feature/your-feature-name`
3. **Make your changes** following our coding standards
4. **Test thoroughly** - ensure your changes don't break existing functionality
5. **Commit** with clear, descriptive messages
6. **Push** to your fork: `git push origin feature/your-feature-name`
7. **Create a Pull Request** with a detailed description

## 📋 Development Guidelines

### Coding Standards
- Follow **WordPress Coding Standards**
- Use **meaningful variable and function names**
- **Comment your code** where necessary
- **Sanitize and validate** all user inputs
- Use **WordPress hooks and filters** appropriately

### Testing Requirements
- Test on **WordPress 5.0+** and **PHP 7.4+**
- Verify compatibility with **common themes and plugins**
- Test all **API integrations** with elnk.pro
- Ensure **responsive design** works on all devices

### Code Structure
```
elnk-pro-shortener/
├── elnk-pro-shortener.php       # Main plugin file
├── includes/                    # Core functionality
│   └── class-elnk-pro-admin.php # Admin class
├── assets/                      # CSS, JS, images
│   ├── admin-script.js         # JavaScript
│   └── admin-style.css         # Styles
└── .github/                    # GitHub workflows
```

## 🛠️ Development Setup

### Prerequisites
- **PHP 7.4+**
- **WordPress 5.0+** (local development environment)
- **Active elnk.pro account** for API testing
- **Git** for version control

### Local Setup
1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/elnk-pro-shortener.git
   ```

2. Install in your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   ln -s /path/to/elnk-pro-shortener elnk-pro-shortener
   ```

3. Activate the plugin in WordPress admin

4. Configure your elnk.pro API credentials in Settings

### Development Tools
- **Code Editor**: VS Code, PHPStorm, or similar
- **Debugging**: Enable WordPress debug mode
- **Testing**: Use WordPress testing environment
- **API Testing**: Use Postman or similar for elnk.pro API testing

## 🧪 Testing Your Changes

### Before Submitting
- [ ] Test all existing functionality still works
- [ ] Test new features thoroughly
- [ ] Check for PHP errors and warnings
- [ ] Verify WordPress coding standards compliance
- [ ] Test with different WordPress themes
- [ ] Test on different PHP versions (7.4, 8.0, 8.1)

### API Testing
- Test with valid elnk.pro credentials
- Test error handling with invalid credentials
- Test rate limiting scenarios
- Test network connectivity issues

## 📝 Pull Request Guidelines

### PR Title Format
- **Fix**: `Fix: Brief description of the bug fix`
- **Feature**: `Feature: Brief description of new feature`
- **Docs**: `Docs: Brief description of documentation changes`
- **Refactor**: `Refactor: Brief description of code improvements`

### PR Description Template
```markdown
## Description
Brief description of changes made.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Code refactoring

## Testing
- [ ] Tested on WordPress 5.0+
- [ ] Tested on PHP 7.4+
- [ ] Tested elnk.pro API integration
- [ ] Tested with common themes/plugins

## Screenshots (if applicable)
Add screenshots to help explain your changes.

## Checklist
- [ ] Code follows WordPress coding standards
- [ ] Self-review of code completed
- [ ] Comments added where necessary
- [ ] Documentation updated if needed
```

## 🏷️ Issue Labels

- **bug**: Something isn't working
- **enhancement**: New feature or request
- **documentation**: Improvements to documentation
- **good first issue**: Good for newcomers
- **help wanted**: Extra attention is needed
- **question**: Further information is requested

## 🎖️ Recognition

Contributors will be:
- **Listed** in the CONTRIBUTORS.md file
- **Mentioned** in release notes for significant contributions
- **Credited** in the plugin's About section

## 📞 Getting Help

- **GitHub Issues**: For bug reports and feature requests
- **Discussions**: For general questions and community chat
- **WordPress.org Support**: For user support questions

## 📜 Code of Conduct

### Our Standards
- **Be respectful** and inclusive
- **Be patient** with newcomers
- **Provide constructive feedback**
- **Focus on the code**, not the person
- **Help others learn** and grow

### Enforcement
Instances of unacceptable behavior may be reported to the project maintainers. All complaints will be reviewed and investigated promptly and fairly.

## 🚀 Development Roadmap

### Planned Features
- **Bulk URL management** improvements
- **Analytics integration** with elnk.pro
- **Custom domain support**
- **Advanced shortcode options**
- **REST API endpoints**

### Current Priorities
1. **Performance optimization**
2. **Enhanced error handling**
3. **Better user experience**
4. **Documentation improvements**

## 📄 License

By contributing to this project, you agree that your contributions will be licensed under the **GPL v2 License**.

---

Thank you for contributing to the elnk.pro Link Shortener Plugin! 🙏

For questions, feel free to open an issue or start a discussion.
