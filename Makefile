.PHONY: up down provision snapshot reset destroy status logs

DOCKER_DIR = Docker
BLUEPRINT_DIR = Blueprint
SNAPSHOT_DIR = snapshots
GOLDEN_SNAPSHOT = $(SNAPSHOT_DIR)/golden.sql.gz
WP = docker exec wpt-wordpress wp --allow-root --path=/var/www/html
MYSQLDUMP = docker exec wpt-wordpress mysqldump --skip-ssl -h db -u wordpress -pwordpress wordpress
MYSQL = docker exec -i wpt-wordpress mysql --skip-ssl -h db -u wordpress -pwordpress wordpress

# WPfaker mode: local | zip | (empty = none)
WPFAKER ?=

# Compose files — add wpfaker override when WPFAKER=local
COMPOSE_FILES = -f docker-compose.yml
ifeq ($(WPFAKER),local)
  COMPOSE_FILES += -f docker-compose.wpfaker.yml
endif

# Start containers (copy Blueprint → Docker first)
up:
	@mkdir -p $(DOCKER_DIR) $(SNAPSHOT_DIR)
	@cp -a $(BLUEPRINT_DIR)/docker-compose.yml $(DOCKER_DIR)/
	@cp -a $(BLUEPRINT_DIR)/docker-compose.wpfaker.yml $(DOCKER_DIR)/
	@cp -a $(BLUEPRINT_DIR)/Caddyfile $(DOCKER_DIR)/
	@cp -a $(BLUEPRINT_DIR)/wp-setup.sh $(DOCKER_DIR)/
	@cp -a $(BLUEPRINT_DIR)/php-uploads.ini $(DOCKER_DIR)/
	@cp -a $(BLUEPRINT_DIR)/acpt-import.php $(DOCKER_DIR)/
	@cd $(DOCKER_DIR) && docker compose $(COMPOSE_FILES) up -d
	@echo "Waiting for WordPress setup..."
	@until $(WP) core is-installed 2>/dev/null; do sleep 2; done
	@echo "WordPress is ready at http://wpfaker-test.dv:8089"

# Stop containers (keep volumes)
down:
	@cd $(DOCKER_DIR) && docker compose $(COMPOSE_FILES) down

# Install plugins, import schemas, create golden snapshot
provision: up
	@WPFAKER=$(WPFAKER) bash $(BLUEPRINT_DIR)/provision.sh
	@$(MAKE) snapshot
	@echo "Provisioning complete. Golden snapshot saved."

# Export current DB as golden snapshot
snapshot:
	@mkdir -p $(SNAPSHOT_DIR)
	@$(MYSQLDUMP) --no-tablespaces | gzip > $(GOLDEN_SNAPSHOT)
	@echo "Snapshot saved to $(GOLDEN_SNAPSHOT) ($$(du -h $(GOLDEN_SNAPSHOT) | cut -f1))"

# Reset DB from golden snapshot (~3 seconds)
reset:
	@test -f $(GOLDEN_SNAPSHOT) || (echo "No snapshot found. Run 'make provision' first." && exit 1)
	@echo "Resetting database..."
	@gunzip -c $(GOLDEN_SNAPSHOT) | $(MYSQL)
	@$(WP) cache flush 2>/dev/null || true
	@echo "Database reset complete."

# Full teardown — removes containers AND volumes
destroy:
	@cd $(DOCKER_DIR) && docker compose $(COMPOSE_FILES) down -v
	@echo "All containers and volumes destroyed."

# Show container status and active plugins
status:
	@cd $(DOCKER_DIR) && docker compose $(COMPOSE_FILES) ps
	@echo ""
	@echo "Active plugins:"
	@$(WP) plugin list --status=active --format=table 2>/dev/null || echo "(WordPress not running)"

# Tail container logs
logs:
	@cd $(DOCKER_DIR) && docker compose $(COMPOSE_FILES) logs -f
