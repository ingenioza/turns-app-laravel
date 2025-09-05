# Turns Laravel API - Postman Collection

This directory contains a comprehensive Postman collection for testing the Turns Laravel API with automated token management and ID copying scripts.

## Files

- `turns-api-collection.json` - Main Postman collection with all API endpoints
- `turns-laravel-environment.json` - Environment variables for the collection

## Features

### 🔐 Automated Token Management
- Automatically extracts and stores authentication tokens from login/register responses
- Token is automatically used in subsequent requests
- No manual token copying required

### 📋 Smart ID Management
- Automatically extracts and stores IDs from API responses (user_id, group_id, turn_id, etc.)
- Uses stored IDs in related endpoints
- Maintains relationships between entities

### 🧪 Built-in Testing
- Pre-configured test scripts for response validation
- Status code verification
- Response structure validation
- Success/error handling

### 🔄 Sequential Testing
- Tests are designed to run in sequence
- Each test builds upon previous results
- Automated workflow from registration to turn management

## Setup Instructions

1. **Import Collection**
   ```
   - Open Postman
   - Click "Import"
   - Select "turns-api-collection.json"
   ```

2. **Import Environment**
   ```
   - In Postman, go to Environments
   - Click "Import"
   - Select "turns-laravel-environment.json"
   - Set as active environment
   ```

3. **Configure Base URL**
   ```
   - Ensure your Laravel server is running on http://turns-laravel.test
   - Or update the base_url variable in the environment
   ```

## Usage Workflow

### 1. Authentication Flow
```
1. Register User - Creates account and stores token
2. Login User - Alternative authentication method
3. Get Current User - Verify authentication
4. Change Password - Update user credentials
5. Update Settings - Modify user preferences
6. Logout - End session
```

### 2. User Management
```
1. List Users - Get all users (stores member_id for groups)
2. Get User - Fetch specific user details
3. Update User - Modify user information
4. Search Users - Find users by query
5. Get Recently Active Users - Activity tracking
```

### 3. Group Management
```
1. Create Group - New group (stores group_id and invite_code)
2. List User Groups - Get user's groups
3. Get Group - Fetch group details
4. Update Group - Modify group information
5. Join Group by Invite Code - Use stored invite_code
6. Get Group Members - List group participants
7. Search Groups - Find groups by query
```

### 4. Turn Management
```
1. Start Turn - Begin new turn (stores turn_id)
2. List User Turns - Get user's turns
3. Get Turn - Fetch turn details
4. Complete Turn - Finish turn with notes
5. Skip Turn - Skip current turn
6. Get Active Turn for Group - Current group turn
7. Get Group Turn History - Historical data
8. Get Group Statistics - Group metrics
9. Get User Statistics - User metrics
```

## Automated Features

### Token Management
All requests after authentication automatically include:
```
Authorization: Bearer {{token}}
```

### ID Extraction Scripts
The collection automatically extracts and stores:
- `user_id` from registration/login responses
- `group_id` from group creation responses
- `turn_id` from turn creation responses
- `member_id` from user listing (for group operations)
- `invite_code` from group creation responses

### Test Validation
Each request includes test scripts that verify:
- Correct HTTP status codes
- Required response fields
- Data structure validation
- Error handling

## Environment Variables

| Variable | Description | Auto-populated |
|----------|-------------|----------------|
| `base_url` | API base URL | Manual |
| `token` | Authentication token | ✅ Auto |
| `user_id` | Current user ID | ✅ Auto |
| `group_id` | Active group ID | ✅ Auto |
| `turn_id` | Active turn ID | ✅ Auto |
| `member_id` | Member for group ops | ✅ Auto |
| `invite_code` | Group invite code | ✅ Auto |

## Running Tests

### Individual Requests
- Click any request and hit "Send"
- View automated test results in the "Test Results" tab

### Collection Runner
1. Click the collection name
2. Click "Run"
3. Select requests to run
4. Click "Run Turns Laravel API"

### Sequential Testing
For best results, run requests in this order:
1. Authentication → Register User
2. Groups → Create Group
3. Turns → Start Turn
4. Continue with other endpoints

## Troubleshooting

### Server Not Running
```
Error: getaddrinfo ENOTFOUND turns-laravel.test
Solution: Ensure Laravel server is running and accessible
```

### Authentication Errors
```
Error: 401 Unauthorized
Solution: Run "Register User" or "Login User" first
```

### Missing IDs
```
Error: Variable not found
Solution: Run prerequisite requests that populate IDs
```

### Environment Issues
```
Error: {{variable}} not resolved
Solution: Ensure environment is selected and imported correctly
```

## Best Practices

1. **Always start with authentication** - Register or login first
2. **Create test data** - Create groups before testing turn operations
3. **Check test results** - Review the test output for each request
4. **Use collection runner** - For comprehensive testing
5. **Monitor console** - Check Postman console for debug information

## Development Tips

### Adding New Endpoints
1. Add request to appropriate folder
2. Include authentication header
3. Add test scripts for validation
4. Update documentation

### Modifying Test Scripts
```javascript
// Template for test scripts
pm.test("Description", function () {
    pm.expect(pm.response.code).to.equal(200);
});

// Store variables
pm.collectionVariables.set('variable_name', value);
```

### Environment Switching
- Create multiple environments (dev, staging, production)
- Switch between them as needed
- Use different base URLs for each environment
