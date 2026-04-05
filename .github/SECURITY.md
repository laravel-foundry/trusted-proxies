# Security Policy

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability, please report it responsibly.

### How to Report

**Please DO NOT file a public issue for security vulnerabilities.**

Instead, please send an email to: **dev@frugan.it**

Include the following information:
- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact assessment
- Any suggested fixes (if available)

### What to Expect

1. **Acknowledgment**: We will acknowledge receipt within 48 hours
2. **Assessment**: We will assess the vulnerability within 5 business days
3. **Resolution**: We will work to resolve critical issues within 30 days
4. **Disclosure**: We will coordinate with you on responsible disclosure

### Responsible Disclosure

We believe in responsible disclosure. We ask that you:
- Give us reasonable time to investigate and fix the issue
- Do not publicly disclose the vulnerability until we've had a chance to fix it
- Do not exploit the vulnerability for malicious purposes

### Security Best Practices

When using this package:

1. **Environment Variables**: Never commit sensitive values to version control
2. **Production Settings**: Use appropriate environment detection in production
3. **Access Control**: Restrict access to configuration files
4. **Regular Updates**: Keep the package and its dependencies updated
5. **Audit Logs**: Monitor proxy header values in production

### Security Features

This package includes several security features:

- **Explicit Trust**: Only IPs from declared providers are trusted
- **Cloudflare Dynamic Fetch**: Cloudflare IP ranges are fetched from the official API and cached, so they stay up to date
- **Symfony Integration**: Relies on Symfony's battle-tested `setTrustedProxies()` mechanism
- **No Header Manipulation**: Does not manipulate `$_SERVER` directly; lets the framework handle header resolution

### Known Security Considerations

- Only enable providers that are actually in use in your infrastructure
- Custom ranges (`TRUSTED_PROXY_CUSTOM_RANGES`) should be as specific as possible
- Use appropriate file permissions for `.env` files

## Security Contact

For security-related questions or concerns:
- Email: dev@frugan.it
- GitHub: [@frugan-dev](https://github.com/frugan-dev)

## Acknowledgments

We appreciate the security research community's efforts in responsibly disclosing vulnerabilities. Security researchers who help us improve this package will be acknowledged in our security advisories (with their permission).
