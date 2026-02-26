#!/bin/bash
set -euo pipefail

# =============================================================================
# ECS Deployment Script
# =============================================================================
# Usage: ./deploy/deploy.sh
#
# Required environment variables:
#   AWS_ACCOUNT_ID  - AWS account ID
#   AWS_REGION      - AWS region (default: us-east-1)
#
# Optional:
#   IMAGE_TAG       - Docker image tag (default: git commit SHA)
#   ECS_CLUSTER     - ECS cluster name (default: ticketing)
# =============================================================================

AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:?AWS_ACCOUNT_ID is required}"
ECR_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
ECR_REPOSITORY="ticketing"
IMAGE_TAG="${IMAGE_TAG:-$(git rev-parse HEAD)}"
ECS_CLUSTER="${ECS_CLUSTER:-ticketing}"
ECS_SERVICE_WEB="ticketing-web"
ECS_SERVICE_WORKER="ticketing-worker"

echo "=== Ticketing ECS Deployment ==="
echo "Registry:  ${ECR_REGISTRY}"
echo "Image Tag: ${IMAGE_TAG}"
echo "Cluster:   ${ECS_CLUSTER}"
echo ""

# Login to ECR
echo "Logging in to ECR..."
aws ecr get-login-password --region "$AWS_REGION" | docker login --username AWS --password-stdin "$ECR_REGISTRY"

# Build production image
echo "Building production image..."
docker build -t "$ECR_REGISTRY/$ECR_REPOSITORY:$IMAGE_TAG" --target=production .
docker tag "$ECR_REGISTRY/$ECR_REPOSITORY:$IMAGE_TAG" "$ECR_REGISTRY/$ECR_REPOSITORY:latest"

# Push to ECR
echo "Pushing to ECR..."
docker push "$ECR_REGISTRY/$ECR_REPOSITORY:$IMAGE_TAG"
docker push "$ECR_REGISTRY/$ECR_REPOSITORY:latest"

# Update ECS services
echo "Updating ECS web service..."
aws ecs update-service \
    --cluster "$ECS_CLUSTER" \
    --service "$ECS_SERVICE_WEB" \
    --force-new-deployment \
    --region "$AWS_REGION"

echo "Updating ECS worker service..."
aws ecs update-service \
    --cluster "$ECS_CLUSTER" \
    --service "$ECS_SERVICE_WORKER" \
    --force-new-deployment \
    --region "$AWS_REGION"

echo ""
echo "Deployment initiated successfully!"
echo "Monitor at: https://${AWS_REGION}.console.aws.amazon.com/ecs/v2/clusters/${ECS_CLUSTER}/services"
