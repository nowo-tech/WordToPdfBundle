# Aggregate demo targets (FrankenPHP under demo/symfony8).

DEMOS := symfony8

.PHONY: help up up8 down update-bundle release-verify release-verify8 release-check release-check8 demo-smoke

help:
	@echo "WordToPdfBundle demo"
	@echo "  make up              Start demo/symfony8"
	@echo "  make up8             Alias for make up"
	@echo "  make down            Stop demo/symfony8"
	@echo "  make update-bundle   Sync mounted bundle in demo/symfony8"
	@echo "  make release-verify  HTTP smoke check (symfony8)"
	@echo "  make release-check   update-bundle + test + verify-http (symfony8)"

up up8:
	@$(MAKE) -C symfony8 up

down:
	@$(MAKE) -C symfony8 down

update-bundle:
	@$(MAKE) -C symfony8 update-bundle

release-verify release-verify8:
	@$(MAKE) -C symfony8 verify-http

release-check release-check8:
	@$(MAKE) -C symfony8 release-check

# REQ-TEST-011: boot + HTTP 200/302
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8022; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT/)"; \
	$(MAKE) -C symfony8 up || exit 1; \
	code=000; \
	i=0; \
	while [ $$i -lt 45 ]; do \
		code=$$(curl -sL -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" || true); \
		[ -z "$$code" ] && code=000; \
		if [ "$$code" = "200" ] || [ "$$code" = "302" ]; then break; fi; \
		i=$$((i+1)); \
		sleep 2; \
	done; \
	$(MAKE) -C symfony8 down 2>/dev/null || true; \
	if [ "$$code" != "200" ] && [ "$$code" != "302" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

# REQ-MAKE-008: update-deps-all
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
