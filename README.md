# Fatora Wallet

Open-source digital wallet system with real payment gateway integrations.

## Features

- Multi-currency wallet support (KWD, SAR, AED, USD)
- Real payment gateway integrations:
  - **KNET** (Kuwait)
  - **PayTabs** (GCC)
  - **MyFatoorah** (GCC)
- RESTful API
- Redis queue support
- Docker-ready for production

## Quick Start

### Docker (Production)

```bash
# Clone and setup
cp .env.example .env
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate
```

### Manual Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## API Endpoints

### Wallet Operations

```bash
# Get user wallets
GET /api/v1/wallet
Authorization: Bearer {token}

# Topup wallet
POST /api/v1/wallet/topup
{
  "amount": 100,
  "currency": "KWD",
  "gateway": "knet"
}

# Check topup status
GET /api/v1/wallet/topup/{transactionId}/status

# Get transactions
GET /api/v1/wallet/transactions?wallet_id=1&type=credit
```

### Test Commands

```bash
# Create test user and wallet
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password"}'

# Topup with KNET
curl -X POST http://localhost/api/v1/wallet/topup \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"amount":50,"currency":"KWD","gateway":"knet"}'

# Get KNET payment form
curl http://localhost/api/knet/payment-form/{transactionId}
```

## Environment Variables

Required for production:

```env
KNET_MERCHANT_ID=your_merchant_id
KNET_SHARED_SECRET=your_shared_secret
DB_PASSWORD=your_database_password
APP_KEY=
```

## License

MIT
