up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f $(service)

ps:
	docker compose ps

db-init:
	docker exec -i smartcity-mysql mysql -u root -proot123 smartcity < database/schema.sql

db-seed:
	docker exec -i smartcity-mysql mysql -u root -proot123 smartcity < database/seed.sql

db-reset:
	docker compose down -v
	docker compose up -d --build

k8s-deploy:
	kubectl apply -f k8s/ -n smartcity

k8s-status:
	kubectl get pods -n smartcity -w

k8s-delete:
	kubectl delete -f k8s/ -n smartcity

clean:
	docker compose down -v
	docker system prune -f