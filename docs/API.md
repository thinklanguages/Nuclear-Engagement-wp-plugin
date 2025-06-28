# API Documentation - Nuclear Engagement Plugin

## Overview

The Nuclear Engagement plugin provides comprehensive REST API endpoints for integrating quiz functionality, TOC management, and external service connections. This documentation covers all available endpoints, authentication methods, and integration patterns.

## Table of Contents

- [Authentication](#authentication)
- [REST API Endpoints](#rest-api-endpoints)
- [External API Integration](#external-api-integration)
- [Webhooks](#webhooks)
- [Rate Limiting](#rate-limiting)
- [Error Handling](#error-handling)
- [Code Examples](#code-examples)

## Authentication

### WordPress Authentication

The Nuclear Engagement plugin uses WordPress's built-in authentication mechanisms:

#### 1. Application Passwords (Recommended)

```http
Authorization: Basic base64(username:application_password)
```

**Setup:**
1. Navigate to **Users > Profile** in WordPress admin
2. Scroll to **Application Passwords** section
3. Create new application password for "Nuclear Engagement API"
4. Use the generated password for API requests

#### 2. Gold Code Authentication

For external Nuclear Engagement app integration:

```json
{
  "gold_code": "your-gold-code-here",
  "site_url": "https://yoursite.com"
}
```

#### 3. JWT Authentication (Optional)

If JWT authentication plugin is installed:

```http
Authorization: Bearer your-jwt-token-here
```

### Permissions

- **Read Access**: `read` capability
- **Write Access**: `edit_posts` capability  
- **Admin Access**: `manage_options` capability

## REST API Endpoints

All endpoints are prefixed with `/wp-json/nuclear-engagement/v1/`

### Quiz Endpoints

#### Get All Quizzes

```http
GET /wp-json/nuclear-engagement/v1/quizzes
```

**Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 10, max: 100)
- `search` (string): Search in quiz titles and content
- `status` (string): Filter by status (`publish`, `draft`, `private`)

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "title": "JavaScript Fundamentals Quiz",
      "slug": "javascript-fundamentals",
      "status": "publish",
      "questions_count": 15,
      "created_date": "2024-01-15T10:00:00Z",
      "modified_date": "2024-01-20T14:30:00Z",
      "author": {
        "id": 1,
        "name": "John Doe"
      },
      "categories": ["programming", "javascript"],
      "settings": {
        "time_limit": 1800,
        "randomize_questions": true,
        "show_results": true
      }
    }
  ],
  "meta": {
    "total": 45,
    "pages": 5,
    "current_page": 1,
    "per_page": 10
  }
}
```

#### Get Single Quiz

```http
GET /wp-json/nuclear-engagement/v1/quizzes/{id}
```

**Response:**
```json
{
  "id": 123,
  "title": "JavaScript Fundamentals Quiz",
  "content": "Test your JavaScript knowledge...",
  "questions": [
    {
      "id": "q1",
      "type": "multiple_choice",
      "question": "What is a closure in JavaScript?",
      "answers": [
        {"id": "a1", "text": "A function inside another function", "correct": true},
        {"id": "a2", "text": "A variable scope", "correct": false},
        {"id": "a3", "text": "A loop construct", "correct": false}
      ],
      "explanation": "A closure is formed when a function..."
    }
  ],
  "settings": {
    "time_limit": 1800,
    "randomize_questions": true,
    "show_results": true,
    "passing_score": 70
  }
}
```

#### Create Quiz

```http
POST /wp-json/nuclear-engagement/v1/quizzes
```

**Request Body:**
```json
{
  "title": "New Quiz Title",
  "content": "Quiz description",
  "status": "draft",
  "questions": [
    {
      "type": "multiple_choice",
      "question": "What is 2 + 2?",
      "answers": [
        {"text": "3", "correct": false},
        {"text": "4", "correct": true},
        {"text": "5", "correct": false}
      ]
    }
  ],
  "settings": {
    "time_limit": 600,
    "randomize_questions": false
  }
}
```

#### Update Quiz

```http
PUT /wp-json/nuclear-engagement/v1/quizzes/{id}
PATCH /wp-json/nuclear-engagement/v1/quizzes/{id}
```

#### Delete Quiz

```http
DELETE /wp-json/nuclear-engagement/v1/quizzes/{id}
```

### Quiz Results Endpoints

#### Submit Quiz Result

```http
POST /wp-json/nuclear-engagement/v1/quizzes/{id}/results
```

**Request Body:**
```json
{
  "user_id": 123,
  "answers": {
    "q1": "a2",
    "q2": "a1",
    "q3": "a3"
  },
  "time_taken": 450,
  "started_at": "2024-01-15T10:00:00Z",
  "completed_at": "2024-01-15T10:07:30Z"
}
```

**Response:**
```json
{
  "result_id": 456,
  "score": 85,
  "passed": true,
  "correct_answers": 12,
  "total_questions": 15,
  "time_taken": 450,
  "feedback": "Excellent work! You scored 85%.",
  "detailed_results": [
    {
      "question_id": "q1",
      "user_answer": "a2",
      "correct_answer": "a2",
      "is_correct": true,
      "explanation": "Correct! A closure is..."
    }
  ]
}
```

#### Get Quiz Results

```http
GET /wp-json/nuclear-engagement/v1/quizzes/{id}/results
```

**Parameters:**
- `user_id` (int): Filter by user ID
- `date_from` (string): Start date (ISO 8601)
- `date_to` (string): End date (ISO 8601)
- `page` (int): Page number
- `per_page` (int): Results per page

### Table of Contents Endpoints

#### Get TOC for Post

```http
GET /wp-json/nuclear-engagement/v1/toc/{post_id}
```

**Response:**
```json
{
  "post_id": 789,
  "toc": [
    {
      "id": "heading-1",
      "text": "Introduction",
      "level": 2,
      "anchor": "#introduction",
      "children": [
        {
          "id": "heading-1-1", 
          "text": "Getting Started",
          "level": 3,
          "anchor": "#getting-started"
        }
      ]
    }
  ],
  "settings": {
    "min_headings": 3,
    "max_depth": 6,
    "exclude_headings": ["h1"]
  }
}
```

#### Generate TOC

```http
POST /wp-json/nuclear-engagement/v1/toc/generate
```

**Request Body:**
```json
{
  "content": "<h2>Chapter 1</h2><p>Content...</p><h3>Section 1.1</h3>",
  "settings": {
    "min_headings": 2,
    "max_depth": 4
  }
}
```

### Analytics Endpoints

#### Get Quiz Analytics

```http
GET /wp-json/nuclear-engagement/v1/analytics/quizzes/{id}
```

**Parameters:**
- `period` (string): `day`, `week`, `month`, `year`
- `start_date` (string): Start date
- `end_date` (string): End date

**Response:**
```json
{
  "quiz_id": 123,
  "period": "month",
  "metrics": {
    "total_attempts": 245,
    "unique_users": 189,
    "average_score": 78.5,
    "completion_rate": 87.2,
    "average_time": 420
  },
  "trends": [
    {"date": "2024-01-01", "attempts": 12, "average_score": 75},
    {"date": "2024-01-02", "attempts": 8, "average_score": 82}
  ]
}
```

#### Get User Performance

```http
GET /wp-json/nuclear-engagement/v1/analytics/users/{user_id}
```

### Settings Endpoints

#### Get Plugin Settings

```http
GET /wp-json/nuclear-engagement/v1/settings
```

#### Update Plugin Settings

```http
POST /wp-json/nuclear-engagement/v1/settings
```

**Request Body:**
```json
{
  "general": {
    "enable_analytics": true,
    "default_time_limit": 1800
  },
  "appearance": {
    "theme": "default",
    "color_scheme": "blue"
  },
  "integrations": {
    "google_analytics_id": "GA-XXXXX-X",
    "zapier_webhook_url": "https://hooks.zapier.com/..."
  }
}
```

## External API Integration

### Nuclear Engagement App API

The plugin communicates with the Nuclear Engagement cloud service for enhanced features:

#### Sync Quiz Data

```http
POST https://api.nuclearengagement.com/v1/sync
```

**Headers:**
```http
Authorization: Bearer {app_token}
Content-Type: application/json
X-Site-URL: https://yoursite.com
```

**Request Body:**
```json
{
  "site_id": "your-site-id",
  "gold_code": "your-gold-code",
  "quizzes": [
    {
      "local_id": 123,
      "title": "Quiz Title",
      "last_modified": "2024-01-15T10:00:00Z"
    }
  ]
}
```

#### Get Cloud Analytics

```http
GET https://api.nuclearengagement.com/v1/analytics
```

### Third-Party Integrations

#### Google Analytics 4

```javascript
// Track quiz completion
gtag('event', 'quiz_completed', {
  'quiz_id': 123,
  'score': 85,
  'time_taken': 420,
  'custom_parameter_1': 'value'
});
```

#### Zapier Webhook

```json
POST https://hooks.zapier.com/hooks/catch/xxxxx/yyyyy/
{
  "event": "quiz_completed",
  "quiz_id": 123,
  "user_email": "user@example.com",
  "score": 85,
  "timestamp": "2024-01-15T10:07:30Z"
}
```

## Webhooks

Configure webhooks for real-time notifications:

### Available Events

- `quiz_created`
- `quiz_updated` 
- `quiz_deleted`
- `quiz_completed`
- `user_registered`
- `settings_updated`

### Webhook Configuration

```php
// In your theme or plugin
add_action('nuclear_engagement_quiz_completed', function($quiz_id, $user_id, $score) {
    wp_remote_post('https://your-webhook-url.com/endpoint', [
        'body' => json_encode([
            'event' => 'quiz_completed',
            'quiz_id' => $quiz_id,
            'user_id' => $user_id,
            'score' => $score,
            'timestamp' => current_time('c')
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Webhook-Secret' => 'your-secret-key'
        ]
    ]);
});
```

## Rate Limiting

### Default Limits

- **Authenticated Users**: 1000 requests per hour
- **Unauthenticated Users**: 100 requests per hour
- **Quiz Submissions**: 10 per user per quiz per hour

### Rate Limit Headers

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 987
X-RateLimit-Reset: 1642678800
```

### Handling Rate Limits

```javascript
if (response.status === 429) {
    const resetTime = response.headers['X-RateLimit-Reset'];
    const waitTime = resetTime - Math.floor(Date.now() / 1000);
    console.log(`Rate limited. Try again in ${waitTime} seconds`);
}
```

## Error Handling

### Error Response Format

```json
{
  "code": "nuclear_engagement_invalid_quiz",
  "message": "Quiz not found or access denied.",
  "data": {
    "status": 404,
    "details": {
      "quiz_id": 123,
      "user_capability": "read"
    }
  }
}
```

### Common Error Codes

| Code | Status | Description |
|------|--------|-------------|
| `nuclear_engagement_unauthorized` | 401 | Invalid authentication |
| `nuclear_engagement_forbidden` | 403 | Insufficient permissions |
| `nuclear_engagement_not_found` | 404 | Resource not found |
| `nuclear_engagement_invalid_data` | 400 | Invalid request data |
| `nuclear_engagement_rate_limited` | 429 | Rate limit exceeded |
| `nuclear_engagement_server_error` | 500 | Internal server error |

## Code Examples

### JavaScript/Fetch API

```javascript
// Get all quizzes
async function getQuizzes() {
    const response = await fetch('/wp-json/nuclear-engagement/v1/quizzes', {
        headers: {
            'Authorization': 'Basic ' + btoa('username:app_password'),
            'Content-Type': 'application/json'
        }
    });
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    return await response.json();
}

// Submit quiz result
async function submitQuizResult(quizId, answers) {
    const result = await fetch(`/wp-json/nuclear-engagement/v1/quizzes/${quizId}/results`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({
            answers: answers,
            started_at: startTime.toISOString(),
            completed_at: new Date().toISOString()
        })
    });
    
    return await result.json();
}
```

### PHP/cURL

```php
// Create a new quiz
function create_quiz($title, $questions) {
    $url = get_rest_url(null, 'nuclear-engagement/v1/quizzes');
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode('username:app_password'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'title' => $title,
            'questions' => $questions,
            'status' => 'publish'
        ])
    ]);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    return json_decode(wp_remote_retrieve_body($response), true);
}
```

### Python/Requests

```python
import requests
import base64

class NuclearEngagementAPI:
    def __init__(self, site_url, username, app_password):
        self.base_url = f"{site_url}/wp-json/nuclear-engagement/v1"
        self.auth = base64.b64encode(f"{username}:{app_password}".encode()).decode()
    
    def get_quizzes(self, page=1, per_page=10):
        headers = {
            'Authorization': f'Basic {self.auth}',
            'Content-Type': 'application/json'
        }
        
        response = requests.get(
            f"{self.base_url}/quizzes",
            headers=headers,
            params={'page': page, 'per_page': per_page}
        )
        
        response.raise_for_status()
        return response.json()
    
    def submit_result(self, quiz_id, answers):
        headers = {
            'Authorization': f'Basic {self.auth}',
            'Content-Type': 'application/json'
        }
        
        response = requests.post(
            f"{self.base_url}/quizzes/{quiz_id}/results",
            headers=headers,
            json={'answers': answers}
        )
        
        return response.json()
```

### React Hook Example

```javascript
import { useState, useEffect } from 'react';

function useNuclearEngagementAPI(endpoint, options = {}) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    
    useEffect(() => {
        async function fetchData() {
            try {
                setLoading(true);
                const response = await fetch(`/wp-json/nuclear-engagement/v1${endpoint}`, {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.wpApiSettings?.nonce,
                        ...options.headers
                    },
                    ...options
                });
                
                if (!response.ok) {
                    throw new Error(`API Error: ${response.status}`);
                }
                
                const result = await response.json();
                setData(result);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        }
        
        fetchData();
    }, [endpoint]);
    
    return { data, loading, error };
}

// Usage
function QuizList() {
    const { data: quizzes, loading, error } = useNuclearEngagementAPI('/quizzes');
    
    if (loading) return <div>Loading quizzes...</div>;
    if (error) return <div>Error: {error}</div>;
    
    return (
        <ul>
            {quizzes?.data?.map(quiz => (
                <li key={quiz.id}>{quiz.title}</li>
            ))}
        </ul>
    );
}
```

## Testing API Endpoints

### Using WordPress CLI

```bash
# Test authentication
wp eval "echo wp_remote_get('https://yoursite.com/wp-json/nuclear-engagement/v1/quizzes');"

# Create test quiz
wp eval "
\$response = wp_remote_post('https://yoursite.com/wp-json/nuclear-engagement/v1/quizzes', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['title' => 'Test Quiz', 'status' => 'draft'])
]);
var_dump(wp_remote_retrieve_body(\$response));
"
```

### Using cURL

```bash
# Get all quizzes
curl -X GET "https://yoursite.com/wp-json/nuclear-engagement/v1/quizzes" \
  -H "Authorization: Basic $(echo -n 'username:app_password' | base64)" \
  -H "Content-Type: application/json"

# Submit quiz result
curl -X POST "https://yoursite.com/wp-json/nuclear-engagement/v1/quizzes/123/results" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: your-nonce-here" \
  -d '{"answers": {"q1": "a2", "q2": "a1"}}'
```

## Best Practices

### Security
- Always use HTTPS in production
- Validate and sanitize all input data
- Use nonces for state-changing operations
- Implement proper capability checks
- Log API access for monitoring

### Performance
- Use pagination for large datasets
- Implement caching where appropriate
- Use database indexes for query optimization
- Consider rate limiting for heavy operations

### Error Handling
- Always check response status codes
- Implement retry logic with exponential backoff
- Log errors for debugging
- Provide meaningful error messages to users

### Integration
- Use webhooks for real-time updates
- Implement proper authentication flow
- Cache frequently accessed data
- Handle network failures gracefully

## Changelog

- **v1.0.0** - Initial API release with quiz and TOC endpoints
- **v1.1.0** - Added analytics endpoints and webhook support
- **v1.2.0** - Enhanced authentication options and rate limiting
- **v1.3.0** - Added external API integration and improved error handling