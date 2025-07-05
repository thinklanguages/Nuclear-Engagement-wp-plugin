#!/bin/bash

echo "Building Playwright test container..."
docker build -f Dockerfile.playwright -t nuclear-engagement-playwright .

echo "Running accessibility tests in Docker..."
docker run --rm \
  --network host \
  -v $(pwd)/test-results:/app/test-results \
  -v $(pwd)/playwright-report:/app/playwright-report \
  nuclear-engagement-playwright