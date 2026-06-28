# Kubernetes Manifests — Smart Traffic & Parking System

## 📋 File Structure

```
k8s/
├── DEPLOYMENT.md                  # Complete deployment guide
├── README.md                      # This file
├── namespace.yaml                 # Kubernetes namespace (smartcity)
├── configmap.yaml                 # Configuration (environment variables)
├── secrets.yaml                   # Sensitive credentials (passwords, keys)
├── mysql-init-scripts.yaml        # Database schema & seed data
├── mysql-statefulset.yaml         # MySQL database with PVC
├── rabbitmq-deployment.yaml       # Message broker with management UI
├── iot-deployment.yaml            # MQTT broker (Mosquitto)
├── oauth-deployment.yaml          # OAuth 2.0 server
├── gateway-deployment.yaml        # API Gateway (load balancer, 2 replicas)
├── php-deployments.yaml           # Citizen, Traffic, Parking services
├── python-ml-deployment.yaml      # ML service with auto-scaling
├── prometheus-deployment.yaml     # Prometheus metrics collector
├── grafana-deployment.yaml        # Grafana dashboards & monitoring
├── hpa.yaml                       # Horizontal Pod Autoscalers
└── ingress.yaml                   # Kubernetes Ingress for routing
```

## 🚀 Quick Deploy (3 Commands)

```bash
# 1. Create namespace & secrets
kubectl apply -f k8s/namespace.yaml k8s/secrets.yaml k8s/configmap.yaml

# 2. Deploy infrastructure (database, message broker, monitoring)
kubectl apply -f k8s/mysql-init-scripts.yaml k8s/mysql-statefulset.yaml \
              k8s/rabbitmq-deployment.yaml k8s/iot-deployment.yaml \
              k8s/prometheus-deployment.yaml k8s/grafana-deployment.yaml

# 3. Deploy application services
kubectl apply -f k8s/oauth-deployment.yaml k8s/gateway-deployment.yaml \
              k8s/php-deployments.yaml k8s/python-ml-deployment.yaml \
              k8s/hpa.yaml k8s/ingress.yaml
```

## 📊 Component Architecture

```
┌─────────────────────────────────────────────────────────┐
│ INGRESS (Kubernetes Ingress / Load Balancer)            │
├─────────────────────────────────────────────────────────┤
│          External Traffic (HTTP/HTTPS)                   │
├─────────────────────────────────────────────────────────┤
│ API GATEWAY (Express.js)                                │
│   - 2 replicas with anti-affinity                        │
│   - JWT verification + Rate limiting                     │
│   - Service routing                                      │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ ┌─────────────┐  ┌──────────────┐  ┌──────────────┐   │
│ │   OAUTH     │  │  CITIZEN SVC │  │  TRAFFIC SVC │   │
│ │   SERVER    │  │   (2 rep)    │  │   (2 rep)    │   │
│ │   (1 rep)   │  └──────────────┘  └──────────────┘   │
│ └─────────────┘                                         │
│                                                           │
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │
│ │ PARKING SVC  │  │ PYTHON ML    │  │ MQTT BROKER  │   │
│ │  (2 rep)     │  │ (2-5 rep HPA)│  │   (1 rep)    │   │
│ └──────────────┘  └──────────────┘  └──────────────┘   │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ ┌────────────────┐  ┌────────────┐  ┌────────────────┐ │
│ │   RABBITMQ     │  │   MYSQL    │  │  PROMETHEUS    │ │
│ │  (1 rep)       │  │ (1 replica)│  │   (1 rep)      │ │
│ └────────────────┘  └────────────┘  └────────────────┘ │
│                                                           │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ GRAFANA (Monitoring Dashboard, 1 replica)          │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## 🔧 Configuration Details

### Services Deployed
- **OAuth Server:** 1 replica, port 3002
- **API Gateway:** 2 replicas, port 3000 (LoadBalancer)
- **Citizen Service:** 2 replicas, port 8000
- **Traffic Service:** 2 replicas, port 8001
- **Parking Service:** 2 replicas, port 8002
- **Python ML Service:** 2-5 replicas (HPA), port 5000
- **MySQL:** 1 StatefulSet replica, port 3306 with PVC
- **RabbitMQ:** 1 replica, port 5672 (AMQP), 15672 (Management UI)
- **MQTT Broker:** 1 replica, port 1883
- **Prometheus:** 1 replica, port 9090
- **Grafana:** 1 replica, port 3000

### Storage
- **MySQL PVC:** 10Gi persistent volume
- **All other services:** emptyDir (ephemeral storage)

### Resource Allocation

| Service | CPU Req | CPU Limit | Memory Req | Memory Limit |
|---------|---------|-----------|-----------|-------------|
| OAuth | 100m | 500m | 256Mi | 512Mi |
| Gateway | 100m | 500m | 256Mi | 512Mi |
| Citizen | 100m | 500m | 256Mi | 512Mi |
| Traffic | 100m | 500m | 256Mi | 512Mi |
| Parking | 100m | 500m | 256Mi | 512Mi |
| ML | 500m | 2000m | 1Gi | 2Gi |
| MySQL | 250m | 1000m | 512Mi | 1Gi |
| RabbitMQ | 250m | 1000m | 512Mi | 1Gi |
| Prometheus | 250m | 1000m | 512Mi | 1Gi |
| Grafana | 100m | 500m | 128Mi | 512Mi |

### Auto-Scaling (HPA)
- **ML Service:** 1-5 replicas, CPU 70%, Memory 80%
- **Citizen Service:** 2-4 replicas, CPU 75%, Memory 85%
- **Traffic Service:** 2-4 replicas, CPU 75%, Memory 85%
- **Parking Service:** 2-4 replicas, CPU 75%, Memory 85%

## 🔐 Security Credentials

⚠️ **IMPORTANT:** Update these in `secrets.yaml` BEFORE deploying to production:

```yaml
# Database
DB_ROOT_PASSWORD: "change_me"
DB_PASSWORD: "change_me"

# JWT & OAuth
JWT_SECRET: "random_32_char_string"
OAUTH_CLIENT_SECRET: "change_me"

# Message Queue
RABBITMQ_PASSWORD: "change_me"

# IoT/MQTT
MQTT_PASSWORD: "change_me"

# Grafana Admin
GF_SECURITY_ADMIN_PASSWORD: "change_me"
```

Generate strong secrets:
```bash
# Generate random strings
openssl rand -base64 32   # For passwords
```

## 🌐 Accessing Services

### Port Forwarding

```bash
# API Gateway (main entry point)
kubectl port-forward -n smartcity svc/api-gateway 3000:80

# Grafana Dashboard
kubectl port-forward -n smartcity svc/grafana 3001:3000

# Prometheus Metrics
kubectl port-forward -n smartcity svc/prometheus 9090:9090

# RabbitMQ Management
kubectl port-forward -n smartcity svc/rabbitmq 15672:15672

# MySQL
kubectl port-forward -n smartcity svc/mysql 3306:3306

# MQTT Broker
kubectl port-forward -n smartcity svc/mosquitto 1883:1883
```

### Service DNS Names (Internal)

Within the cluster, services are accessible via:
- `mysql.smartcity.svc.cluster.local:3306`
- `rabbitmq.smartcity.svc.cluster.local:5672`
- `api-gateway.smartcity.svc.cluster.local:80`
- `citizen-service.smartcity.svc.cluster.local:8000`
- `traffic-service.smartcity.svc.cluster.local:8001`
- `parking-service.smartcity.svc.cluster.local:8002`
- `python-ml-service.smartcity.svc.cluster.local:5000`
- `mosquitto.smartcity.svc.cluster.local:1883`
- `prometheus.smartcity.svc.cluster.local:9090`
- `grafana.smartcity.svc.cluster.local:3000`

## 🔍 Monitoring

### Prometheus Scrape Targets
- API Gateway (/metrics)
- OAuth Server (/metrics)
- All PHP Services (/metrics)
- Python ML Service (/metrics)
- RabbitMQ management API
- MySQL exporter

### Grafana Dashboards
Pre-configured dashboard includes:
1. Request Rate (req/s) per service
2. Error Rate (%) per service
3. Response Latency P95 (ms)
4. Container CPU Usage (%)
5. Container Memory Usage (MB)
6. RabbitMQ Queue Depth
7. Database Connection Pool
8. ML Predictions Count

## 📦 Docker Images

Make sure to build and push images to your registry:

```bash
# Build all images
for service in express-gateway oauth-server php-citizen php-traffic php-parking python-ml-service; do
  docker build -t your-registry/$service:latest ./$service
  docker push your-registry/$service:latest
done

# Update image references in YAML files
sed -i 's|image: \([a-z-]*\):latest|image: your-registry/\1:latest|g' k8s/*.yaml
```

## ⚙️ Customization

### Update Image Registry
```bash
# Replace all image references
find k8s -name "*.yaml" -exec sed -i 's|image: |image: your-registry/|g' {} \;
```

### Update Domain (for Ingress)
Edit `ingress.yaml`:
```yaml
- host: smartcity.example.com  # Change to your domain
```

### Adjust Resource Limits
Edit deployment files and modify `resources` section:
```yaml
resources:
  requests:
    cpu: 100m        # Minimum guaranteed
    memory: 256Mi
  limits:
    cpu: 500m        # Maximum allowed
    memory: 512Mi
```

## 🆘 Troubleshooting

### Check Pod Status
```bash
kubectl get pods -n smartcity -o wide
kubectl describe pod -n smartcity <pod-name>
kubectl logs -n smartcity <pod-name> -f
```

### MySQL Initialization
```bash
# Check MySQL logs
kubectl logs -n smartcity mysql-0

# Manual schema application
kubectl exec -n smartcity mysql-0 -- mysql -u root -p${DB_ROOT_PASSWORD} < database/schema.sql
```

### Service Discovery
```bash
# Test DNS from within cluster
kubectl run -it --rm debug --image=busybox:latest --restart=Never -n smartcity -- sh
# nslookup mysql.smartcity.svc.cluster.local
```

### Restart Deployment
```bash
kubectl rollout restart deployment/api-gateway -n smartcity
```

## 📚 Deployment Order

1. `namespace.yaml` — Create namespace
2. `secrets.yaml` — Create secrets
3. `configmap.yaml` — Create configuration
4. `mysql-init-scripts.yaml` — Create DB initialization
5. `mysql-statefulset.yaml` — Deploy MySQL
6. `rabbitmq-deployment.yaml` — Deploy RabbitMQ
7. `iot-deployment.yaml` — Deploy MQTT Broker
8. `prometheus-deployment.yaml` — Deploy Prometheus
9. `grafana-deployment.yaml` — Deploy Grafana
10. `oauth-deployment.yaml` — Deploy OAuth
11. `gateway-deployment.yaml` — Deploy API Gateway
12. `php-deployments.yaml` — Deploy PHP services
13. `python-ml-deployment.yaml` — Deploy ML service
14. `hpa.yaml` — Deploy auto-scalers
15. `ingress.yaml` — Deploy Ingress

## 📖 Additional Resources

- [Complete Deployment Guide](./DEPLOYMENT.md)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [kubectl Cheat Sheet](https://kubernetes.io/docs/reference/kubectl/cheatsheet/)

---

**Last Updated:** 2026-06-28  
**K8s Version:** 1.28+  
**Status:** Production Ready
