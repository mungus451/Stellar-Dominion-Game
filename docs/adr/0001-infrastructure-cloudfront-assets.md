# ADR 0001 — CloudFront + S3 assets, API Gateway origin, ACM, and Route53

Status: Accepted

Date: 2025-09-08

## Context

We operate a PHP-based serverless application deployed with the Serverless Framework. The app serves dynamic API requests (PHP on Lambda via Bref) and static assets (CSS, JS, images, favicon, robots.txt). Requirements:

- Serve static assets from a performant CDN with edge caching.
- Secure access to S3-backed assets (no public bucket exposure).
- Route dynamic requests to API Gateway / Lambda with low latency and correct caching/forwarding behavior.
- Use a custom domain (sd.rayprogramming.com).
- Automate deployments and asset uploads in CI/CD while avoiding race conditions where uploads run before the S3 bucket exists.
- Use TLS for custom domains and automate certificate management where possible.

## Decision

We will implement the following architecture and operational decisions:

- Use a single CloudFront distribution to serve both dynamic and static content.
  - Default origin: API Gateway (HttpApi). Default cache behavior forwards query strings and cookies and allows API HTTP methods.
  - Secondary origin: S3 Assets bucket (private, not public). Cache behaviors route `/assets/*`, `/favicon.ico`, and `/robots.txt` to this origin.
- Protect the S3 buckets using a CloudFront Origin Access Identity (OAI). S3 bucket policies grant only the OAI permission to GetObject.
- Create a single S3 bucket for assets and file storage with public access blocked and versioning enabled.
- Use ACM certificates in us-east-1 for CloudFront distributions and wire Route53 alias records (both A and AAAA) to CloudFront using the global hosted zone ID `Z2FDTNDATAQYW2`.
- Use DNS validation for ACM certificates and create the necessary Route53 validation records programmatically in the DNS CloudFormation template.
- Use Serverless Framework to manage Lambda, API Gateway, and CloudFormation resources. Break CF resources into small templates (buckets, cdn, dns, database) and include them via `resources: - ${file(...)}` in `serverless.yml`.
- Upload static assets to the S3 bucket as a post-deploy step using a Node script `scripts/sync-assets.js` invoked by `serverless-scriptable-plugin` hook `after:deploy:deploy`. The script:
  - Derives bucket name from service/stage or accepts an explicit `BUCKET` env var.
  - Verifies bucket existence via `aws s3api head-bucket` and exits 0 (skip) if the bucket is not yet present (avoids failing the deploy).
  - Uploads `Stellar-Dominion/public/assets` to `s3://<bucket>/assets/` and copies `favicon.ico` and `robots.txt` to the bucket root.
- Keep certificate creation and validation in the DNS stack; ensure it is deployed in us-east-1 when used by CloudFront.

## Decision drivers

- Security: S3 assets must not be publicly accessible; access via CloudFront OAI only.
- Performance: CDN caching at edge locations improves performance for static assets.
- Operational simplicity: Use Serverless Framework constructs and a small post-deploy sync to automate uploads while avoiding race conditions.
-- Cost: Use one CloudFront distribution and a single S3 bucket (combined assets and file storage) to separate concerns while minimizing resources.

## Alternatives considered

1. Public S3 bucket with CloudFront Origin Access disabled and public objects
   - Pros: Simpler setup for uploads and testing.
   - Cons: Public buckets are less secure; accidental exposure risk; harder to enforce origin-only access patterns.
2. Separate CloudFront distributions per origin (one for API, one for static assets)
   - Pros: Fine-grained cache/control, independent lifecycles.
   - Cons: Higher management overhead and additional cost for multiple distributions.
3. Upload assets during pre-deploy (current initial approach)
   - Pros: Assets are present before deploy completes.
   - Cons: Race condition — pre-deploy upload can fail when CF/CFN hasn't created the S3 bucket yet. Requires robust pre-checks or separate step.

We chose the combined distribution, OAI, and post-deploy upload to balance security, cost and operational simplicity.

## Consequences

-- The S3 bucket must exist before uploads occur. Using `after:deploy:deploy` and a head-bucket check avoids failing deploys.
- CloudFront distributions and ACM certs must be created in us-east-1; DNS/Certificate templates must either be deployed in us-east-1 or certificate provisioning must be separated into its own us-east-1 stack.
- DNS validation for certificates requires maintenance of Route53 records; the template currently creates the validation record automatically.
- The architecture assumes the CloudFormation logical name for the HttpApi (`HttpApi`) is available when the CDN template is evaluated; if the HttpApi is created in a separate stack, we must export/import the domain between stacks.

## Operational tasks / Next steps

- Ensure CI/CD environment has AWS credentials with permissions to:
  - Create/modify CloudFormation stacks, ACM certificates (us-east-1), Route53 records, S3 buckets and object uploads.
- Add a small retry/poll to `scripts/sync-assets.js` to wait for bucket creation for a short window (optional).
- If deploying across regions, split certificate/cdn resources into a us-east-1 stack and export the certificate ARN for cross-region reference.
- Add TTL cache controls and invalidation patterns for asset updates (consider invalidating on new deployments or using content-hashed filenames).
- Add monitoring/alerting for distribution health and certificate expiration.

## Stakeholders

- Platform/DevOps team: maintain CI/CD and infra templates.
- App developers: ensure assets are built into `Stellar-Dominion/public/assets` before deploy.

## References

- AWS ADR guidance: https://docs.aws.amazon.com/prescriptive-guidance/latest/architectural-decision-records/adr-process.html
- CloudFront & ACM: https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/using-https-altnames.html
- Origin Access Identity: https://docs.aws.amazon.com/AmazonS3/latest/dev/website-hosting-custom-domain-w-cloudfront.html

---

Decision recorded by: platform automation
