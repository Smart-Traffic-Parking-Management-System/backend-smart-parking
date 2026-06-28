# Kubernetes Deployment Guide — Smart Traffic & Parking System

## Prerequisites

Ensure you have the following tools installed:
- `kubectl` (1.28+)
- `docker` — to build and push images
- Active Kubernetes cluster (minikube, EKS, GKE, AKS, or any K8s distribution)

## Quick Start

### Step 1: Build Docker Images

Build all service images and push to your registry:

```bash
# Build images (example using Docker)
docker build -t api-gateway:latest ./express-gateway
docker build -t oauth-server:latest ./oauth-server
docker build -t citizen-service:latest ./php-citizen
docker build -t traffic-service:latest ./php-traffic
docker build -t parking-service:latest ./php-parking
docker build -t python-ml-service:latest ./python-ml-service

# For public registry (e.g., Docker Hub or AWS ECR)
docker tag api-gateway:latest your-registry/api-gateway:latest
docker tag oauth-server:latest your-registry/oauth-server:latest
docker tag citizen-service:latest your-registry/citizen-service:latest
docker tag traffic-service:latest your-registry/traffic-service:latest
docker tag parking-service:latest your-registry/parking-service:latest
docker tag python-ml-service:latest your-registry/python-ml-service:latest

docker push your-registry/api-gateway:latest
docker push your-registry/oauth-server:latest
docker push your-registry/citizen-service:latest
docker push your-registry/traffic-service:latest
docker push your-registry/parking-service:latest
docker push your-registry/python-ml-service:latest
```

> **Note:** Update all image references in the YAML files (*.yaml) to point to your registry.

### Step 2: Update Secrets

Edit `k8s/secrets.yaml` and update all `GANTI_INI` placeholders with actual values:

```bash
# Generate JWT_SECRET (example using openssl)
openssl rand -base64 32

# Generate strong passwords
# You need to update these in secrets.yaml:
# - DB_ROOT_PASSWORD
# - DB_PASSWORD
# - JWT_SECRET
# - OAUTH_CLIENT_SECRET
# - RABBITMQ_PASSWORD
# - MQTT_PASSWORD
```

Apply secrets:
```bash
kubectl apply -f k8s/secrets.yaml
```

### Step 3: Apply ConfigMaps and Namespace

```bash
# Create namespace
kubectl apply -f k8s/namespace.yaml

# Create ConfigMap
kubectl apply -f k8s/configmap.yaml
```

### Step 4: Deploy Infrastructure Components

```bash
# Deploy MySQL (database)
kubectl apply -f k8s/mysql-statefulset.yaml

# Wait for MySQL to be ready
kubectl wait --for=condition=ready pod -l app=mysql -n smartcity --timeout=300s

# Apply database schema (optional - can be automated in init container)
# kubectl exec -n smartcity mysql-0 -- mysql -u root -p${DB_ROOT_PASSWORD} < database/schema.sql
# kubectl exec -n smartcity mysql-0 -- mysql -u root -p${DB_ROOT_PASSWORD} < database/seed.sql

# Deploy RabbitMQ
kubectl apply -f k8s/rabbitmq-deployment.yaml

# Wait for RabbitMQ
kubectl wait --for=condition=ready pod -l app=rabbitmq -n smartcity --timeout=300s

# Deploy MQTT Broker (Mosquitto)
kubectl apply -f k8s/iot-deployment.yaml

# Deploy Prometheus (monitoring)
kubectl apply -f k8s/prometheus-deployment.yaml

# Deploy Grafana (dashboards)
kubectl apply -f k8s/grafana-deployment.yaml
```

### Step 5: Deploy Application Services

```bash
# Deploy OAuth Server
kubectl apply -f k8s/oauth-deployment.yaml

# Deploy API Gateway
kubectl apply -f k8s/gateway-deployment.yaml

# Deploy PHP Services (Citizen, Traffic, Parking)
kubectl apply -f k8s/php-deployments.yaml

# Deploy Python ML Service
kubectl apply -f k8s/python-ml-deployment.yaml

# Apply HPA (Auto-scaling)
kubectl apply -f k8s/hpa.yaml

# Deploy Ingress (for external routing)
kubectl apply -f k8s/ingress.yaml
```

### Step 6: Verify Deployment

```bash
# Check all resources in smartcity namespace
kubectl get all -n smartcity

# Check pods status
kubectl get pods -n smartcity -w

# Check services
kubectl get svc -n smartcity

# Check deployments
kubectl get deploy -n smartcity

# Check HPA status
kubectl get hpa -n smartcity
```

### Step 7: Access Services

#### API Gateway (Entry Point)
```bash
# Port forward to access locally
kubectl port-forward -n smartcity svc/api-gateway 3000:80

# Access: http://localhost:3000
```

#### Grafana (Monitoring Dashboard)
```bash
# Port forward
kubectl port-forward -n smartcity svc/grafana 3001:3000

# Access: http://localhost:3001
# Default credentials: admin / admin_password_change_me (from secrets.yaml)
```

#### Prometheus (Metrics Database)
```bash
# Port forward
kubectl port-forward -n smartcity svc/prometheus 9090:9090

# Access: http://localhost:9090
```

#### RabbitMQ Management UI
```bash
# Port forward
kubectl port-forward -n smartcity svc/rabbitmq 15672:15672

# Access: http://localhost:15672
# Credentials: smartcity / ${RABBITMQ_PASSWORD}
```

## Deployment Manifests Overview

| File | Component | Replicas | Port |
|------|-----------|----------|------|
| namespace.yaml | Kubernetes Namespace | — | — |
| configmap.yaml | Configuration (non-secret) | — | — |
| secrets.yaml | Secrets (passwords, keys) | — | — |
| mysql-statefulset.yaml | MySQL Database | 1 | 3306 |
| rabbitmq-deployment.yaml | Message Broker | 1 | 5672, 15672 |
| iot-deployment.yaml | MQTT Broker | 1 | 1883, 9001 |
| oauth-deployment.yaml | OAuth 2.0 Server | 1 | 3002 |
| gateway-deployment.yaml | API Gateway | 2 | 3000 |
| php-deployments.yaml | Services (Citizen, Traffic, Parking) | 2 ea | 8000, 8001, 8002 |
| python-ml-deployment.yaml | ML Service | 2 | 5000 |
| prometheus-deployment.yaml | Prometheus (metrics) | 1 | 9090 |
| grafana-deployment.yaml | Grafana (dashboards) | 1 | 3000 |
| hpa.yaml | HorizontalPodAutoscalers | — | — |
| ingress.yaml | Kubernetes Ingress | — | 80, 443 |

## Scaling Configuration

### HPA (Horizontal Pod Autoscaling)

- **Python ML Service:** 1-5 replicas (CPU 70%, Memory 80%)
- **Citizen Service:** 2-4 replicas (CPU 75%, Memory 85%)
- **Traffic Service:** 2-4 replicas (CPU 75%, Memory 85%)
- **Parking Service:** 2-4 replicas (CPU 75%, Memory 85%)

### Resource Requests & Limits

Each service has defined:
- **requests:** Guaranteed resources for scheduling
- **limits:** Maximum resources allowed (OOMKill if exceeded)

Example for Python ML:
```yaml
resources:
  requests:
    cpu: 500m
    memory: 1Gi
  limits:
    cpu: 2000m
    memory: 2Gi
```

## Monitoring & Observability

### Prometheus Targets

Prometheus scrapes metrics from:
- API Gateway (:3000/metrics)
- OAuth Server (:3002/metrics)
- All PHP Services (:8000-8002/metrics)
- Python ML Service (:5000/metrics)
- RabbitMQ (:15692)

### Grafana Dashboards

Pre-configured dashboard includes:
1. Request Rate (req/s) per Service
2. Error Rate (%) per Service
3. Response Latency P95 (ms)
4. Container CPU Usage (%)
5. Container Memory Usage (MB)
6. RabbitMQ Queue Depth
7. Database Connection Pool
8. ML Predictions Count/min

## Troubleshooting

### Check Pod Logs
```bash
kubectl logs -n smartcity <pod-name> -f
```

### Describe Pod (for events)
```bash
kubectl describe pod -n smartcity <pod-name>
```

### Check Resource Usage
```bash
kubectl top nodes
kubectl top pods -n smartcity
```

### Check Service DNS
```bash
# Inside a pod, test DNS resolution
kubectl run -it --rm debug --image=busybox --restart=Never -n smartcity -- sh
# nslookup mysql.smartcity.svc.cluster.local
```

### Reset Deployment
```bash
# Delete namespace (removes all resources)
kubectl delete namespace smartcity

# Recreate from scratch
kubectl apply -f k8s/namespace.yaml
# ... repeat steps 2-7
```

## Production Checklist

- [ ] Update all image references to your private registry
- [ ] Change all default passwords in `secrets.yaml`
- [ ] Configure persistent storage (not just emptyDir)
- [ ] Set up cert-manager for TLS certificates
- [ ] Configure RBAC policies for services
- [ ] Enable network policies for security
- [ ] Set up backup strategy for MySQL data
- [ ] Configure alerting rules in Prometheus
- [ ] Test disaster recovery procedures
- [ ] Document your cluster topology and architecture
- [ ] Plan capacity for expected load
- [ ] Implement logging aggregation (ELK, Loki)

## Notes

- **MySQL:** Default root password is set in `secrets.yaml`. Change it before production!
- **RabbitMQ:** Initial user is `smartcity`. Add additional users as needed.
- **Grafana:** Default admin credentials are in `grafana-admin` secret.
- **Ingress:** Update `smartcity.example.com` to your actual domain.
- **Images:** Update all Docker image references (`image: xxx:latest`) in YAML files to match your registry.

## Additional Resources

- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [kubectl Cheat Sheet](https://kubernetes.io/docs/reference/kubectl/cheatsheet/)
- [Helm Package Manager](https://helm.sh/) — for easier multi-file deployment

---

**Last Updated:** 2026-06-28
**Version:** 1.0
