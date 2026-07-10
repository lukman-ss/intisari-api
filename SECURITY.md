# Security Policy

## Supported Versions

Currently, only the latest major release of the Intisari API receives active security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Scope of the Repository

This repository covers the core Intisari API starter template and its built-in authentication, middleware, and routing configurations. 
Third-party dependencies managed by Composer (e.g., `php`, `lukman-ss/intisari`) are outside the direct scope of this repository's security patches and should be reported to their respective upstream maintainers.

## Reporting a Vulnerability

**Do NOT report active security vulnerabilities through public GitHub issues.**

To report a vulnerability privately, please use the **GitHub Security Advisories** feature for this repository:
1. Go to the **Security** tab of this repository.
2. Click on **Report a vulnerability** to open a private advisory draft.

### Required Information
When reporting, please include as much detail as possible to help us reproduce and resolve the issue:
- A description of the vulnerability and its impact.
- Steps to reproduce the issue (including any specific HTTP requests, payloads, or configurations).
- The version of the project you are testing.
- Potential mitigations you might suggest.

### Expected Response Process
We review all submitted reports. However, as an open-source project maintained by volunteers, we do not guarantee a specific SLA for triage or resolution. 
1. We will acknowledge receipt of your vulnerability report once reviewed.
2. If confirmed, we will work on a patch in a private fork.
3. We will notify you when a fix is ready for release.

## Safe Harbor
We encourage responsible, non-destructive security research. 
- Please use your own local instances for penetration testing. 
- Do not perform testing against live production environments without explicit authorization. 
- Any testing should not degrade services, destroy data, or violate user privacy.

## Verifying Security Releases
When a security vulnerability is fixed, we will issue a new patch release. 
You can verify the fix by checking the release notes or the associated CVE/Security Advisory published on GitHub. We strongly recommend running `composer security:check` in your CI/CD pipeline to automatically catch known dependency vulnerabilities and risky patterns in your own deployments.
