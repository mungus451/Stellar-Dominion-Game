#!/bin/bash
# Build AWS Parameters and Secrets Extension for Lambda
# This script builds a custom extension if needed, but AWS provides official layers

set -e

echo "=== AWS Secrets Manager Extension Build Script ==="
echo "Note: AWS provides official layers that are recommended over custom builds"
echo ""

# Check if we're in AWS Lambda environment
if [ -n "$AWS_LAMBDA_FUNCTION_NAME" ]; then
    echo "Running in Lambda environment - no build needed"
    exit 0
fi

echo "AWS Official Layers by Region:"
echo "us-east-1: arn:aws:lambda:us-east-1:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo "us-east-2: arn:aws:lambda:us-east-2:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo "us-west-1: arn:aws:lambda:us-west-1:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo "us-west-2: arn:aws:lambda:us-west-2:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo "eu-west-1: arn:aws:lambda:eu-west-1:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo ""
echo "Current configuration uses: arn:aws:lambda:us-east-2:177933569100:layer:AWS-Parameters-and-Secrets-Lambda-Extension:11"
echo ""
echo "Extension will be available at: http://localhost:2773"
echo "Use environment variables to configure:"
echo "  PARAMETERS_SECRETS_EXTENSION_CACHE_ENABLED=true"
echo "  PARAMETERS_SECRETS_EXTENSION_HTTP_PORT=2773"
echo ""
echo "No manual build required - using AWS-provided layer!"
