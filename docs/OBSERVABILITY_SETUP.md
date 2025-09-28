# AWS X-Ray and Lambda Insights Observability Setup

## Overview
Your Stellar Dominion game now has comprehensive observability configured using native AWS services:

- **AWS X-Ray**: Distributed tracing for request flow analysis
- **Lambda Insights**: Performance monitoring for CPU, memory, and cold starts
- **Custom Sampling Rules**: Cost-optimized tracing focused on high-value endpoints
- **RDS Performance Insights**: Database performance monitoring (manual setup required)

## What's Been Configured

### 1. X-Ray Tracing
✅ **Enabled globally** for all Lambda functions in `serverless.yml`:
```yaml
provider:
  tracing:
    lambda: true
```

✅ **IAM permissions** added for X-Ray operations:
- `xray:PutTraceSegments` - Submit trace data
- `xray:PutTelemetryRecords` - Submit telemetry  
- `xray:GetSamplingRules` - Retrieve sampling configuration

### 2. Lambda Insights  
✅ **Plugin installed**: `serverless-plugin-lambda-insights@2.0.0`

✅ **Configuration added**:
```yaml
custom:
  lambdaInsights:
    defaultLambdaInsights: true
```

✅ **CloudWatch permissions** for metrics and logs

### 3. Custom Sampling Rules
✅ **Cost-optimized sampling rules** in `infrastructure/observability.yml`:
- **Default**: 1% sampling (most endpoints) 
- **Game Actions**: 10% sampling for `POST /api/*`
- **Authentication**: 20% sampling for `/login*`
- **Database Operations**: 5% sampling for `/profile*`

## Deployment Instructions

### 1. Deploy the Configuration
```bash
cd /home/jray/code/Stellar-Dominion-Game
npm install  # Install Lambda Insights plugin
serverless deploy --stage prod  # Deploy with observability
```

### 2. Manual RDS Performance Insights Setup
**Performance Insights cannot be enabled via CloudFormation on existing Aurora clusters.**

To enable manually:
1. Go to **AWS RDS Console**
2. Select your cluster: `starlight-dominion` 
3. Click **Modify**
4. Scroll to **Performance Insights**
5. Enable Performance Insights
6. Set retention: **7 days (free)** or longer (paid)
7. **Apply changes immediately**

### 3. Verify X-Ray Setup
After deployment, check:
1. **AWS X-Ray Console** → Service Map
2. You should see your Lambda functions appearing after traffic
3. **Note**: HTTP API Gateway won't show in traces (only REST APIs support X-Ray)

### 4. View Lambda Insights
1. **AWS Lambda Console** → Functions → [Function Name]
2. Click **Monitoring** tab
3. View **Lambda Insights** section for:
   - CPU utilization
   - Memory usage
   - Init duration
   - Performance anomalies

## Cost Expectations

### X-Ray Costs (us-east-2)
- **First 100K traces/month**: FREE
- **Additional traces**: ~$5 per 1M stored
- **Trace retrieval**: First 1M free, then ~$0.50 per 1M
- **Custom sampling rules**: Keep costs minimal by default

### Lambda Insights Costs
- **Standard CloudWatch pricing** for logs/metrics
- **No per-request charges**
- Approximately **$0.50 per 1GB of ingested logs**

### Performance Insights Costs
- **7 days retention**: FREE
- **Longer retention**: ~$0.02 per vCPU-hour

## Using the Observability Data

### X-Ray Service Map
- **View request flow**: API Gateway → Lambda → Database
- **Identify bottlenecks**: Slow database queries, cold starts
- **Error analysis**: See where requests fail in the chain

### Lambda Insights Dashboards
- **Cold start optimization**: Monitor init duration trends
- **Memory optimization**: Right-size Lambda memory allocation
- **CPU analysis**: Identify compute-bound operations

### Performance Insights
- **Database load**: CPU, connections, throughput
- **Top SQL statements**: Identify slow queries
- **Wait events**: Find database bottlenecks

## HTTP API Limitation
⚠️ **Important**: Your HTTP API won't appear in X-Ray traces (only REST APIs support X-Ray). 

**Options**:
1. **Keep current setup**: You'll see Lambda traces and database calls
2. **Migrate critical endpoints** to REST API with X-Ray enabled for full end-to-end visibility

## Troubleshooting

### No X-Ray Traces Appearing
1. Verify Lambda functions have X-Ray enabled: `aws lambda get-function --function-name <name>`
2. Check IAM permissions in CloudWatch Logs
3. Generate traffic to trigger sampling

### Lambda Insights Not Showing
1. Confirm Lambda Insights layer is attached to functions
2. Check CloudWatch Logs for insights data
3. Wait 5-10 minutes for initial data to appear

### Performance Insights Empty
1. Ensure Performance Insights is enabled on Aurora cluster
2. Generate database traffic
3. Data appears within 5-15 minutes

## Next Steps

1. **Deploy immediately** to start collecting baseline metrics
2. **Enable RDS Performance Insights** manually
3. **Monitor for 1 week** to establish performance baselines
4. **Optimize based on insights**: 
   - Right-size Lambda memory
   - Optimize slow database queries
   - Reduce cold start frequency

## Additional Resources
- [AWS X-Ray Developer Guide](https://docs.aws.amazon.com/xray/latest/devguide/)
- [Lambda Insights Documentation](https://docs.aws.amazon.com/lambda/latest/dg/monitoring-insights.html)
- [Performance Insights Guide](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/USER_PerfInsights.html)