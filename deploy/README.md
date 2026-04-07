# AWS ECS Deployment Guide

## Architecture Overview

```
Internet → ALB (HTTPS:443) → ECS Service (web) → Container (port 80)
                              ECS Service (worker) → Container (queue + scheduler)
                              ↕
                         RDS MySQL 8.0
                         ElastiCache Redis 7
                         SQS Queue
                         S3 Bucket
```

## AWS Infrastructure Requirements

### Compute & Containers

| Resource | Configuration |
|----------|--------------|
| **ECR Repository** | `ticketing` |
| **ECS Cluster** | EC2 launch type, 2+ instances (t3.medium recommended) |
| **ECS Service (web)** | Desired count: 2, attached to ALB target group |
| **ECS Service (worker)** | Desired count: 1, no ALB attachment |

### Networking

| Resource | Configuration |
|----------|--------------|
| **VPC** | 2+ AZs with public and private subnets |
| **ALB** | Internet-facing, HTTPS listener with ACM certificate |
| **Target Group** | HTTP:80, health check path: `/up`, interval: 30s |

### Data Stores

| Resource | Configuration |
|----------|--------------|
| **RDS MySQL 8.0** | Multi-AZ (production), Single-AZ (staging), `db.t3.medium` |
| **ElastiCache Redis 7** | Cluster mode (production), Single-node (staging), `cache.t3.micro` |
| **SQS** | Standard queue: `ticketing-default` |
| **S3** | Bucket: `ticketing-storage`, versioning enabled |

### Email & Logging

| Resource | Configuration |
|----------|--------------|
| **SES** | Verified domain for transactional email |
| **CloudWatch Log Group** | `/ecs/ticketing`, retention: 30 days |

### Security

| Resource | Configuration |
|----------|--------------|
| **SSM Parameter Store** | All secrets under `/ticketing/` prefix |
| **IAM Execution Role** | `ecsTaskExecutionRole` — ECR pull, SSM read, CloudWatch logs |
| **IAM Task Role** | `ecsTaskRole` — S3, SQS, SES access |

### Security Groups

| Security Group | Inbound Rules |
|----------------|---------------|
| **ALB SG** | 443 from 0.0.0.0/0 |
| **ECS SG** | 80 from ALB SG |
| **RDS SG** | 3306 from ECS SG |
| **Redis SG** | 6379 from ECS SG |

## SSM Parameters to Create

```bash
aws ssm put-parameter --name /ticketing/APP_KEY --type SecureString --value "base64:..."
aws ssm put-parameter --name /ticketing/DB_HOST --type SecureString --value "ticketing.xxxxx.us-east-1.rds.amazonaws.com"
aws ssm put-parameter --name /ticketing/DB_DATABASE --type String --value "ticketing"
aws ssm put-parameter --name /ticketing/DB_USERNAME --type SecureString --value "ticketing"
aws ssm put-parameter --name /ticketing/DB_PASSWORD --type SecureString --value "your-password"
aws ssm put-parameter --name /ticketing/REDIS_HOST --type String --value "ticketing.xxxxx.cache.amazonaws.com"
aws ssm put-parameter --name /ticketing/AWS_ACCESS_KEY_ID --type SecureString --value "AKIA..."
aws ssm put-parameter --name /ticketing/AWS_SECRET_ACCESS_KEY --type SecureString --value "..."
aws ssm put-parameter --name /ticketing/AWS_DEFAULT_REGION --type String --value "us-east-1"
aws ssm put-parameter --name /ticketing/AWS_BUCKET --type String --value "ticketing-storage"
aws ssm put-parameter --name /ticketing/SQS_PREFIX --type String --value "https://sqs.us-east-1.amazonaws.com/123456789"
aws ssm put-parameter --name /ticketing/SQS_QUEUE --type String --value "ticketing-default"
```

## Deployment

### Manual Deployment

```bash
export AWS_ACCOUNT_ID=123456789012
export AWS_REGION=us-east-1

./deploy/deploy.sh
```

### CI/CD (GitHub Actions)

Automated deployment is triggered on push to `main`. See `.github/workflows/deploy.yml`.

**GitHub Secrets required:**
- `AWS_ACCESS_KEY_ID` — IAM user with ECR push + ECS deploy permissions
- `AWS_SECRET_ACCESS_KEY` — Corresponding secret key

**GitHub Variables required:**
- `AWS_REGION` — e.g., `us-east-1`
- `AWS_ACCOUNT_ID` — AWS account ID

## Auto Scaling

Recommended ECS service auto-scaling policy:

- **Target tracking**: CPU utilization at 70%
- **Min capacity**: 2 (web), 1 (worker)
- **Max capacity**: 10 (web), 4 (worker)
- **Scale-in cooldown**: 300s
- **Scale-out cooldown**: 60s

## First-Time Setup Checklist

1. Create VPC with 2+ AZs (public + private subnets)
2. Create ECR repository: `ticketing`
3. Create RDS MySQL 8.0 instance
4. Create ElastiCache Redis 7 cluster
5. Create SQS queue: `ticketing-default`
6. Create S3 bucket: `ticketing-storage`
7. Create CloudWatch log group: `/ecs/ticketing`
8. Create SSM parameters (see above)
9. Create IAM roles (`ecsTaskExecutionRole`, `ecsTaskRole`)
10. Create ALB with HTTPS listener + ACM certificate
11. Create ECS cluster (EC2 launch type)
12. Register task definitions: `deploy/ecs/task-definition.json` and `task-definition-worker.json`
13. Create ECS services (web + worker)
14. Configure auto-scaling
15. Verify SES domain
16. Set up GitHub Actions secrets
