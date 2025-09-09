#!/usr/bin/env bash
set -euo pipefail

# Build and publish the Secrets Manager Agent Lambda Layer
# Usage:
#   AWS_ACCOUNT_ID=123456789012 LAYER_NAME=secrets-manager-agent-extension ./scripts/build_publish_agent.sh

AWS_ACCOUNT_ID=${AWS_ACCOUNT_ID:-}
LAYER_NAME=${LAYER_NAME:-secrets-manager-agent-extension}
TARGET_TRIPLE=${TARGET_TRIPLE:-x86_64-unknown-linux-gnu}

if [ -z "$AWS_ACCOUNT_ID" ]; then
  echo "ERROR: AWS_ACCOUNT_ID must be set (export AWS_ACCOUNT_ID=... )"
  exit 1
fi

if ! command -v cargo >/dev/null 2>&1; then
  echo "ERROR: cargo not found. Install Rust toolchain (https://rustup.rs/)"
  exit 1
fi

if ! command -v aws >/dev/null 2>&1; then
  echo "ERROR: aws CLI not found. Install and configure AWS CLI (https://aws.amazon.com/cli/)"
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  echo "ERROR: jq not found. Install jq."
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "ERROR: zip not found. Install zip."
  exit 1
fi

echo "Building release (target: $TARGET_TRIPLE)"
# Build the release binary for the target
cargo build --release --target=$TARGET_TRIPLE

# Prepare packaging directories
rm -rf build_tmp
mkdir -p build_tmp/bin build_tmp/extensions

# Copy binary
cp ./target/$TARGET_TRIPLE/release/aws_secretsmanager_agent build_tmp/bin/secrets-manager-agent

# Copy extension example if present
if [ -f aws_secretsmanager_agent/examples/example-lambda-extension/secrets-manager-agent-extension.sh ]; then
  cp aws_secretsmanager_agent/examples/example-lambda-extension/secrets-manager-agent-extension.sh build_tmp/extensions/
else
  echo "Warning: example extension script not found, proceeding with only binary"
fi

# Zip contents
pushd build_tmp >/dev/null
zip -r ../secrets-manager-agent-extension.zip .
popd >/dev/null

# Publish layer
echo "Publishing layer $LAYER_NAME to AWS account $AWS_ACCOUNT_ID"
LAYER_VERSION_ARN=$(aws lambda publish-layer-version \
  --layer-name "$LAYER_NAME" \
  --zip-file "fileb://secrets-manager-agent-extension.zip" \
  --compatible-runtimes provided 2>/dev/null | jq -r '.LayerVersionArn')

if [ -z "$LAYER_VERSION_ARN" ] || [ "$LAYER_VERSION_ARN" = "null" ]; then
  echo "Failed to publish layer. Check AWS credentials and permissions."
  exit 1
fi

echo "Published layer: $LAYER_VERSION_ARN"

echo "Cleanup"
rm -rf build_tmp

# Print instructions to deploy using the new ARN
cat <<EOF
To use the layer in serverless deploy, run:

export SECRETS_MANAGER_AGENT_LAYER_ARN=$LAYER_VERSION_ARN
sls deploy --stage prod --verbose
EOF
