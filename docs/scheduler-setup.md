# Symfony Scheduler Setup for Subscription Processing

## Overview

The ZigZag system uses Symfony Scheduler component to automatically process recurring subscription payments. The scheduler runs every 5 minutes and handles:

- Processing subscriptions due for billing
- Retrying failed payment attempts (max 3 retries)
- Updating subscription billing dates
- Managing subscription status based on payment results

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  SubscriptionScheduleProvider                               │
│  - Runs every 5 minutes                                     │
│  - Dispatches ProcessSubscriptionsMessage                   │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Symfony Messenger (async transport)                        │
│  - Routes message to RabbitMQ                               │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  ProcessSubscriptionsMessageHandler                         │
│  - Finds subscriptions due for billing                      │
│  - Creates payments via PaymentProcessor                    │
│  - Updates subscription next billing date                   │
│  - Handles failures and retries                             │
└─────────────────────────────────────────────────────────────┘
```

## Components

### 1. ProcessSubscriptionsMessage
**File:** `src/Message/ProcessSubscriptionsMessage.php`

A simple DTO that carries configuration for the subscription processing task:
- `limit` (default: 100) - Maximum subscriptions to process per run
- `processRetries` (default: true) - Whether to process failed payment retries

### 2. ProcessSubscriptionsMessageHandler
**File:** `src/MessageHandler/ProcessSubscriptionsMessageHandler.php`

The main business logic handler that:
- Queries subscriptions due for billing using `SubscriptionRepository::findDueForBilling()`
- Creates payments using `PaymentProcessor::createPayment()`
- Updates subscription state (next billing date, failed payment count)
- Handles failures with exponential backoff logic
- Marks subscriptions as `PAYMENT_FAILED` after 3 failed attempts

### 3. SubscriptionScheduleProvider
**File:** `src/Scheduler/SubscriptionScheduleProvider.php`

Defines the schedule using Symfony's `#[AsSchedule]` attribute:
- Runs every 5 minutes
- Uses stateful schedule to prevent duplicate execution
- Dispatches `ProcessSubscriptionsMessage` to the async transport

## Configuration

### Enable Symfony Scheduler
**File:** `config/packages/scheduler.yaml`

```yaml
framework:
    scheduler:
        enabled: true
```

### Configure Messenger Routing
**File:** `config/packages/messenger.yaml`

```yaml
framework:
    messenger:
        routing:
            App\Message\ProcessSubscriptionsMessage: async
```

This routes the message to RabbitMQ for asynchronous processing.

## Deployment

### Production Setup with Supervisord (Recommended)

Create a supervisord configuration for the scheduler worker:

**File:** `/etc/supervisor/conf.d/scheduler.conf`

```ini
[program:scheduler_worker]
command=/usr/local/bin/php /var/www/html/bin/console messenger:consume scheduler_default --time-limit=3600
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
startsecs=5
startretries=3
numprocs=1
stdout_logfile=/var/log/scheduler_worker.log
stderr_logfile=/var/log/scheduler_worker_error.log
```

Then reload supervisord:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start scheduler_worker
```

### Docker Compose Setup

Add a scheduler service to your `docker-compose.yml`:

```yaml
services:
  scheduler:
    build: .
    command: php bin/console messenger:consume scheduler_default -vv
    depends_on:
      - php
      - rabbitmq
      - mysql
      - redis
    environment:
      - DATABASE_URL=${DATABASE_URL}
      - MESSENGER_TRANSPORT_DSN=${MESSENGER_TRANSPORT_DSN}
      - REDIS_URL=${REDIS_URL}
    restart: unless-stopped
```

### Kubernetes Setup

Create a Deployment for the scheduler worker:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: scheduler-worker
spec:
  replicas: 1  # Only 1 replica to avoid duplicate processing
  selector:
    matchLabels:
      app: scheduler-worker
  template:
    metadata:
      labels:
        app: scheduler-worker
    spec:
      containers:
      - name: scheduler
        image: your-app-image:latest
        command: ["php", "bin/console", "messenger:consume", "scheduler_default", "--time-limit=3600"]
        env:
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: database-url
        - name: MESSENGER_TRANSPORT_DSN
          value: "amqp://rabbitmq:5672"
```

## Monitoring

### Logs

Monitor scheduler execution logs:

```bash
# Docker
docker compose logs -f scheduler

# Kubernetes
kubectl logs -f deployment/scheduler-worker

# Supervisord
tail -f /var/log/scheduler_worker.log
```

### Key Log Messages

**Successful Processing:**
```
[info] Starting scheduled subscription processing
[info] Found subscriptions due for billing {"count":5}
[info] Subscription payment processed {"subscription_id":123,"payment_id":456}
[info] Subscription processing completed {"processed":5,"failed":0}
```

**Failed Processing:**
```
[error] Subscription payment failed {"subscription_id":123,"error":"Payment provider unavailable"}
[error] Subscription marked as PAYMENT_FAILED {"subscription_id":123,"failed_attempts":3}
```

### Metrics to Monitor

1. **Processing Rate**: Number of subscriptions processed per run
2. **Failure Rate**: Percentage of failed subscription payments
3. **Queue Depth**: Number of pending messages in `scheduler_default` queue
4. **Processing Time**: Time taken to process each batch
5. **Retry Count**: Number of subscriptions in retry state

### Health Checks

Check if scheduler worker is running:

```bash
# Supervisord
sudo supervisorctl status scheduler_worker

# Docker
docker compose ps scheduler

# Check RabbitMQ queue
docker compose exec rabbitmq rabbitmqctl list_queues name messages
```

## Manual Execution

For testing or emergency processing, you can manually run the command:

```bash
# Process subscriptions with default settings
php bin/console app:process-subscriptions

# Dry run (no actual changes)
php bin/console app:process-subscriptions --dry-run

# Limit processing to 50 subscriptions
php bin/console app:process-subscriptions --limit=50
```

## Troubleshooting

### Issue: Scheduler not running

**Check 1: Is the worker consuming messages?**
```bash
docker compose exec php php bin/console messenger:stats
```

**Check 2: Is the schedule being generated?**
```bash
docker compose exec php php bin/console debug:scheduler
```

**Check 3: Are messages in the queue?**
```bash
docker compose exec rabbitmq rabbitmqctl list_queues
```

### Issue: Duplicate subscription processing

This can happen if multiple scheduler workers are running simultaneously.

**Solution:**
- Ensure only 1 scheduler worker is running
- The schedule is marked as `stateful()` which should prevent duplicates
- Check for multiple supervisord processes or Docker containers

### Issue: Subscriptions not being processed

**Check 1: Query the database directly**
```sql
SELECT id, next_billing_date, status
FROM subscription
WHERE status = 'active'
AND next_billing_date <= CURDATE();
```

**Check 2: Check for errors in logs**
```bash
docker compose logs scheduler | grep ERROR
```

**Check 3: Manually trigger processing**
```bash
php bin/console app:process-subscriptions -vv
```

## Performance Tuning

### Batch Size
Adjust the `limit` parameter in `SubscriptionScheduleProvider`:

```php
new ProcessSubscriptionsMessage(
    limit: 200,  // Process up to 200 subscriptions per run
    processRetries: true
)
```

### Frequency
Adjust the schedule frequency in `SubscriptionScheduleProvider`:

```php
RecurringMessage::every('10 minutes', ...)  // Run every 10 minutes instead of 5
```

### Parallel Processing
If you need higher throughput, you can run multiple messenger workers:

```bash
# Start 3 workers for parallel processing
php bin/console messenger:consume scheduler_default &
php bin/console messenger:consume scheduler_default &
php bin/console messenger:consume scheduler_default &
```

**Note:** The `IdempotencyService` with distributed locking ensures no duplicate payments even with parallel workers.

## Testing

### Unit Test Example

```php
use App\Message\ProcessSubscriptionsMessage;
use App\MessageHandler\ProcessSubscriptionsMessageHandler;
use PHPUnit\Framework\TestCase;

class ProcessSubscriptionsMessageHandlerTest extends TestCase
{
    public function testProcessesDueSubscriptions(): void
    {
        // Setup mocks
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $paymentProcessor = $this->createMock(PaymentProcessor::class);

        // Create handler
        $handler = new ProcessSubscriptionsMessageHandler(
            $subscriptionRepository,
            $paymentProcessor,
            $this->entityManager,
            $this->logger
        );

        // Execute
        $message = new ProcessSubscriptionsMessage(limit: 100);
        $handler($message);

        // Assert
        // ...
    }
}
```

### Integration Test

```bash
# Use dry-run to test without making actual payments
php bin/console app:process-subscriptions --dry-run -vv
```

## Migration from Cron Job

If you're migrating from a cron-based setup:

1. **Stop the existing cron job**
   ```bash
   crontab -e
   # Comment out or remove the subscription processing cron entry
   ```

2. **Start the scheduler worker**
   ```bash
   docker compose up -d scheduler
   # or
   sudo supervisorctl start scheduler_worker
   ```

3. **Monitor for 24 hours** to ensure subscriptions are being processed correctly

4. **Remove the cron entry permanently** once verified

## Benefits over Cron Jobs

1. **Better Error Handling**: Automatic retries via Symfony Messenger
2. **Monitoring**: Built-in metrics and logging
3. **Scalability**: Can run multiple workers for parallel processing
4. **Reliability**: Message persistence in RabbitMQ ensures no lost jobs
5. **Flexibility**: Easy to adjust frequency without system changes
6. **Testability**: Can be tested via message dispatch
7. **Integration**: Works seamlessly with existing Messenger infrastructure

## Related Documentation

- [Symfony Scheduler Component](https://symfony.com/doc/current/scheduler.html)
- [Symfony Messenger Component](https://symfony.com/doc/current/messenger.html)
- [Payment Implementation Guide](./PAYMENT_IMPLEMENTATION.md)
- [Subscription Entity](../src/Entity/Subscription.php)
