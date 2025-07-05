#!/bin/bash

# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready..."
sleep 10

# Run WP-CLI commands to create test post
docker run --rm \
  --network nuclear-engagement-plugin_default \
  --volumes-from wp_test \
  wordpress:cli \
  wp post create \
    --post_title="Test Post for Nuclear Engagement" \
    --post_name="test-post-for-nuclear-engagement" \
    --post_status="publish" \
    --post_content='<h2>First Heading</h2><p>This is test content for accessibility testing.</p><h3>Subheading</h3><p>More content here.</p>[nuclen_quiz][nuclen_toc][nuclen_summary]' \
    --allow-root \
    --path=/var/www/html

echo "Test post created!"