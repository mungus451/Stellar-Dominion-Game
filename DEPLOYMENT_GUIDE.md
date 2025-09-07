# Stellar Dominion Deployment Guide

This guide explains how to deploy Stellar Dominion to AWS using the Serverless Framework.

## Architecture Overview

The application deploys the following AWS resources:

```
┌─────────────────────────────────────────────────────────────────┐
│                        AWS Infrastructure                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────┐    ┌─────────────────┐    ┌──────────────┐ │
│  │   API Gateway   │    │   Lambda        │    │  Aurora      │ │
│  │                 │◄──►│   Functions     │◄──►│  Serverless  │ │
│  │ HTTP API        │    │                 │    │  v2          │ │
│  └─────────────────┘    │ • api           │    └──────────────┘ │
│                         │ • turnProcessor │                     │
│                         └─────────────────┘                     │
│                                  │                              │
│  ┌─────────────────┐             │           ┌─────────────────┐ │
│  │   S3 Bucket     │◄────────────┘           │   DynamoDB      │ │
│  │                 │                         │                 │ │
│  │ File Storage    │                         │ Session Storage │ │
│  └─────────────────┘                         └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐    ┌─────────────────┐                    │
│  │ Secrets Manager │    │   CloudWatch    │                    │
│  │                 │    │                 │                    │
│  │ DB Credentials  │    │ Logs & Events   │                    │
│  │ (Auto-rotation) │    │                 │                    │
│  └─────────────────┘    └─────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

## Prerequisites

### 1. Required Software
```bash
# Node.js 18+ and npm
node --version  # Should be 18.x or higher
npm --version

# Serverless Framework
npm install -g serverless
sls --version

# PHP 8.1 and Composer
php --version   # Should be 8.1.x
composer --version

# AWS CLI (configured with credentials)
aws --version
aws sts get-caller-identity  # Verify credentials
```

### 2. AWS Permissions Required

Your AWS credentials need these permissions:
- **Lambda**: Full access for function deployment
- **API Gateway**: Create/manage HTTP APIs
- **S3**: Create/manage buckets
- **DynamoDB**: Create/manage tables
- **Secrets Manager**: Create/manage secrets and rotation
- **IAM**: Create execution roles
- **CloudFormation**: Deploy stacks
- **VPC**: Access existing VPC resources (if using VPC)

### 3. Environment Setup

```bash
# Clone the repository
git clone https://github.com/mungus451/Stellar-Dominion-Game.git
cd Stellar-Dominion-Game

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies  
npm install
```

## Pre-Deployment Configuration

### 1. VPC Configuration (Required for Aurora)

Update the VPC settings in `serverless.yml`:

```yaml
custom:
  vpcConfig:
    securityGroupIds:
      - sg-YOUR-SECURITY-GROUP-ID    # Lambda to Aurora access
    subnetIds:
      - subnet-YOUR-PRIVATE-SUBNET-1  # Private subnet AZ-1
      - subnet-YOUR-PRIVATE-SUBNET-2  # Private subnet AZ-2
      - subnet-YOUR-PRIVATE-SUBNET-3  # Private subnet AZ-3
```

### 2. Database Configuration

Update the Aurora cluster endpoint in `serverless.yml`:

```yaml
provider:
  environment:
    DB_HOST: your-aurora-cluster.cluster-xxxxx.us-east-2.rds.amazonaws.com
```

### 3. Environment Variables

Set the database password:

```bash
# Set initial database password (will be rotated automatically)
export DB_PASSWORD="your-secure-password-here"
```

## Deployment Commands

### 1. Deploy to Development

```bash
# Deploy to dev stage
sls deploy --stage dev

# Check deployment status
sls info --stage dev
```

### 2. Deploy to Production

```bash
# Deploy to production stage
sls deploy --stage prod

# Verify production deployment
sls info --stage prod
```

### 3. Deploy Individual Functions (Faster)

```bash
# Deploy only the API function
sls deploy function --function api --stage dev

# Deploy only the turn processor
sls deploy function --function turnProcessor --stage dev
```

## Post-Deployment Configuration

### 1. Database Setup

After first deployment, you need to:

1. **Connect to Aurora** using the initial credentials
2. **Run database migrations** to create tables
3. **Update Secrets Manager** with the correct password if needed

```bash
# Example: Connect to database and run setup
mysql -h your-aurora-cluster.cluster-xxxxx.us-east-2.rds.amazonaws.com -u stellar -p
# Run your database setup scripts
```

### 2. Secrets Manager Password

1. Go to **AWS Secrets Manager** console
2. Find secret: `starlight-dominion-db-credentials-{stage}`
3. **Update password** if needed (will trigger rotation)
4. **Test rotation** to ensure it works properly

### 3. Manual CDN Setup (Now Automatic!)

✅ **CDN is now automatically deployed** via `serverless-lift` website construct:

1. **CloudFront Distribution** - Automatically created
2. **Origin Access Control** - Automatically configured  
3. **CLOUDFRONT_DOMAIN** - Automatically set via `${construct:website.cname}`

No manual CDN setup required!

## Environment-Specific Configurations

### Development Stage
- **Purpose**: Testing and development
- **Cost**: Minimal (pay-per-request)
- **Features**: All resources created but minimal scale
- **Database**: Can use smaller Aurora instance

### Production Stage  
- **Purpose**: Live application
- **Cost**: Optimized for scale
- **Features**: All resources with production settings
- **Database**: Full Aurora Serverless v2 auto-scaling

## Monitoring and Logs

### CloudWatch Logs

```bash
# View API function logs
sls logs --function api --stage prod --tail

# View turn processor logs
sls logs --function turnProcessor --stage prod --tail

# View logs from specific time
sls logs --function api --stage prod --startTime 1h
```

### CloudWatch Metrics

Monitor these key metrics:
- **Lambda Duration**: Function execution time
- **Lambda Errors**: Function failures
- **API Gateway 4xx/5xx**: HTTP errors
- **DynamoDB Throttles**: Session storage issues
- **Secrets Manager API Calls**: Cost monitoring

## Cost Optimization

### Expected Costs (Monthly)

**Development Stage:**
- Lambda: $0-5 (low usage)
- API Gateway: $0-1 (few requests)
- DynamoDB: $0-1 (on-demand)
- S3: $0-1 (minimal storage)
- Aurora: $20-50 (minimum charges)
- **Total: ~$25-60/month**

**Production Stage:**
- Lambda: $5-50 (depends on usage)
- API Gateway: $1-10 (per million requests)
- DynamoDB: $1-20 (session storage)
- S3: $1-10 (file storage)
- Aurora: $50-200 (auto-scaling)
- **Total: ~$60-300/month**

### Cost Reduction Tips

1. **Use CloudFront CDN** for S3 (reduces S3 requests by 90%+)
2. **Monitor Lambda duration** (optimize cold starts)
3. **Use DynamoDB wisely** (avoid scans, use TTL)
4. **Aurora scaling** (set appropriate min/max capacity)
5. **Cleanup unused stages** (`sls remove --stage old-stage`)

## Troubleshooting

### Common Deployment Issues

**1. VPC Configuration Errors**
```
Error: The provided execution role does not have permissions to call CreateNetworkInterface on EC2
```
Solution: Add VPC permissions to deployment IAM role

**2. Aurora Connection Timeouts**
```
Error: ETIMEDOUT connecting to database
```
Solution: Check security group allows Lambda access to Aurora (port 3306)

**3. Secrets Manager Access Denied**
```
Error: User is not authorized to perform: secretsmanager:GetSecretValue
```
Solution: Verify IAM permissions for Secrets Manager access

**4. Large Package Size**
```
Error: Code storage limit exceeded
```
Solution: Check package exclusions in `serverless.yml`, run `composer install --no-dev`

### Debugging Commands

```bash
# Check CloudFormation stack
aws cloudformation describe-stacks --stack-name starlight-dominion-dev

# Test Lambda function locally
sls invoke local --function api --data '{}'

# Get function information
sls info --verbose --stage dev

# Remove deployment (careful!)
sls remove --stage dev
```

### Log Analysis

```bash
# Search for errors in logs
aws logs filter-log-events \
  --log-group-name /aws/lambda/starlight-dominion-dev-api \
  --filter-pattern "ERROR"

# Monitor real-time logs
aws logs tail /aws/lambda/starlight-dominion-dev-api --follow
```

## Security Considerations

### 1. Secrets Management
- ✅ Database credentials in Secrets Manager
- ✅ Automatic 30-day rotation enabled
- ✅ No hardcoded passwords in code

### 2. Network Security
- ✅ Lambda functions in private VPC
- ✅ Aurora in private subnets
- ✅ Security groups restrict access

### 3. S3 Security
- ✅ Public access blocked by default
- ⚠️ CORS allows all origins (restrict in production)
- 🔄 Versioning enabled for data recovery

### 4. IAM Security
- ✅ Least privilege permissions
- ✅ Role-based access (no user credentials in Lambda)
- ✅ Resource-specific ARNs in policies

## Backup and Recovery

### 1. Database Backups
- ✅ Aurora automatic backups enabled
- ✅ Point-in-time recovery available
- ✅ Cross-region backup replication (configure if needed)

### 2. Application Code
- ✅ Source code in Git repository
- ✅ Deployment artifacts in S3 (Serverless)
- ✅ CloudFormation templates for infrastructure

### 3. User Data
- ✅ S3 versioning enabled
- ✅ DynamoDB point-in-time recovery enabled
- 🔄 Consider cross-region replication for critical data

## Scaling Considerations

### Lambda Scaling
- **Concurrent executions**: Default limit 1,000 (request increase if needed)
- **Memory allocation**: Currently 1024MB (tune based on usage)
- **Timeout**: 28 seconds for API, adjust for turn processor

### Aurora Scaling
- **Auto-scaling**: Enabled, configure min/max ACUs
- **Reader instances**: Add for read-heavy workloads
- **Connection pooling**: Implement for high concurrency

### DynamoDB Scaling
- **On-demand mode**: Automatically scales
- **Monitor throttling**: Switch to provisioned if needed
- **Session TTL**: Properly configured for automatic cleanup

This deployment guide provides everything needed to successfully deploy and manage Stellar Dominion on AWS.
