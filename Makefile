.PHONY: up down provision snapshot reset destroy status logs

DOCKER_DIR = Docker
BLUEPRINT_DIR = Blueprint
SNAPSHOT_DIR = snapshots
GOLDEN_SNAPSHOT = $(SNAPSHOT_DIR)/golden.sql.gz
WP = docker exec wpt-wordpress wp --allow-root --path=/var/www/html

# Start containers (copy Blueprint → Docker first)
up:
	@mkdir -p $(DOCKER_DIR) $(SNAPSHOT_DIR)
	@cp $(BLUEPRINT_DIR)/docker-compose.yml $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/Caddyfile $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/wp-setup.sh $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/php-uploads.ini $(DOCKER_DIR)/
	@cd $(DOCKER_DIR) && docker compose up -d
	@echo "Waiting for WordPress setup..."
	@until $(WP) core is-installed 2>/dev/null; do sleep 2; done
	@echo "WordPress is ready at http://wpfaker-test.dv"

# Stop containers (keep volumes)
down:
	@cd $(DOCKER_DIR) && docker compose down

# Install plugins, import schemas, create golden snapshot
provision: up
	@bash $(BLUEPRINT_DIR)/provision.sh
	@$(MAKE) snapshot
	@echo "Provisioning complete. Golden snapshot saved."

# Export current DB as golden snapshot
snapshot:
	@mkdir -p $(SNAPSHOT_DIR)
	@$(WP) db export - | gzip > $(GOLDEN_SNAPSHOT)
	@echo "Snapshot saved to $(GOLDEN_SNAPSHOT) ($$(du -h $(GOLDEN_SNAPSHOT) | cut -f1))"

# Reset DB from golden snapshot (~3 seconds)
reset:
	@test -f $(GOLDEN_SNAPSHOT) || (echo "No snapshot found. Run 'make provision' first." && exit 1)
	@echo "Resetting database..."
	@gunzip -c $(GOLDEN_SNAPSHOT) | $(WP) db import -
	@$(WP) cache flush 2>/dev/null || true
	@echo "Database reset complete."

# Full teardown — removes containers AND volumes
destroy:
	@cd $(DOCKER_DIR) && docker compose down -v
	@echo "All containers and volumes destroyed."

# Show container status and active plugins
status:
	@cd $(DOCKER_DIR) && docker compose ps
	@echo ""
	@echo "Active plugins:"
	@$(WP) plugin list --status=active --format=table 2>/dev/null || echo "(WordPress not running)"

# Tail container logs
logs:
	@cd $(DOCKER_DIR) && docker compose logs -f
