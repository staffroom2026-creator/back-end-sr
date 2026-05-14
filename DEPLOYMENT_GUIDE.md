# StaffRoom Backend - Deployment Guide

This guide provides instructions on how to deploy the StaffRoom Node.js API to a production environment.

## Prerequisites
- Node.js (v14 or higher recommended)
- MySQL Server
- Process Manager (PM2 recommended)
- Nginx (for reverse proxy)

## 1. Environment Setup

Clone the repository to your production server:
```bash
git clone <repository-url> /var/www/staffroom
cd /var/www/staffroom
```

Install dependencies:
```bash
npm install --production
```

## 2. Configuration

Create a `.env` file in the root directory based on `.env.example`:
```bash
cp .env.example .env
```

Edit the `.env` file with your production database credentials and application settings:
```ini
NODE_ENV=production
PORT=5000

# Database
DB_HOST=localhost
DB_USER=production_user
DB_PASSWORD=production_password
DB_NAME=staffroom_db

# JWT Secret
JWT_SECRET=your_super_secret_production_key_here
JWT_EXPIRES_IN=90d
```

## 3. Database Initialization

Ensure the MySQL server is running, then initialize the database tables:
```bash
npm run db:init
```

## 4. Starting the Application with PM2

Install PM2 globally if you haven't already:
```bash
npm install -g pm2
```

Start the application:
```bash
pm2 start src/server.js --name "staffroom-api"
```

Set PM2 to start automatically on system reboot:
```bash
pm2 startup
pm2 save
```

## 5. Reverse Proxy with Nginx (Recommended)

To serve the API securely over HTTPS and route traffic from port 80/443 to your Node.js app on port 5000, configure Nginx.

Create a new Nginx configuration file:
```bash
sudo nano /etc/nginx/sites-available/staffroom
```

Add the following configuration:
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;

    location / {
        proxy_pass http://localhost:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # Optional: Serve uploads directly via Nginx for better performance
    location /uploads/ {
        alias /var/www/staffroom/uploads/;
    }
}
```

Enable the configuration and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/staffroom /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 6. SSL Configuration (Let's Encrypt)

Secure your API with HTTPS using Certbot:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.yourdomain.com
```

## 7. Maintenance

**View Logs:**
```bash
pm2 logs staffroom-api
```

**Restart Application:**
```bash
pm2 restart staffroom-api
```
