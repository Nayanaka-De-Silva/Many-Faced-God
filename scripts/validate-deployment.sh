#!/bin/bash
set -e

echo "🔍 Validating deployment configuration..."
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Check if .env file exists
echo "📄 Checking .env file..."
if [ ! -f .env ]; then
    echo -e "${RED}❌ ERROR: .env file not found${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✅ .env file exists${NC}"
fi

# Check if APP_KEY is set
echo ""
echo "🔑 Validating APP_KEY..."
if [ -f .env ]; then
    APP_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    
    if [ -z "$APP_KEY" ]; then
        echo -e "${RED}❌ ERROR: APP_KEY is empty${NC}"
        echo "   Run: docker-compose run --rm app php artisan key:generate --show --no-ansi"
        ERRORS=$((ERRORS + 1))
    else
        echo -e "${GREEN}✅ APP_KEY is set${NC}"
        
        # Validate format
        if [[ ! $APP_KEY =~ ^base64: ]]; then
            echo -e "${RED}❌ ERROR: APP_KEY must start with 'base64:' prefix${NC}"
            echo "   Current: $APP_KEY"
            ERRORS=$((ERRORS + 1))
        else
            echo -e "${GREEN}✅ APP_KEY has correct format (base64: prefix)${NC}"
            
            # Check for ANSI escape codes
            if echo "$APP_KEY" | grep -q $'\e\|\\033\|\\e'; then
                echo -e "${RED}❌ ERROR: APP_KEY contains ANSI escape codes${NC}"
                echo "   Use --no-ansi flag when generating"
                ERRORS=$((ERRORS + 1))
            else
                echo -e "${GREEN}✅ APP_KEY is clean (no escape codes)${NC}"
            fi
            
            # Validate length (should be base64: + 44 chars = ~51 total)
            KEY_LENGTH=${#APP_KEY}
            if [ $KEY_LENGTH -lt 40 ]; then
                echo -e "${YELLOW}⚠️  WARNING: APP_KEY seems too short (length: $KEY_LENGTH)${NC}"
                WARNINGS=$((WARNINGS + 1))
            else
                echo -e "${GREEN}✅ APP_KEY length is valid ($KEY_LENGTH chars)${NC}"
            fi
        fi
    fi
fi

# Check cipher configuration (if config/app.php exists)
echo ""
echo "🔐 Validating cipher configuration..."
if [ -f config/app.php ]; then
    CIPHER=$(grep "'cipher'" config/app.php | grep -oP "(?<=')[^']+(?=')" | head -1)
    if [ -n "$CIPHER" ]; then
        echo -e "${GREEN}✅ Cipher configured: $CIPHER${NC}"
        
        # Validate it's a supported cipher
        case $CIPHER in
            AES-128-CBC|AES-256-CBC|AES-128-GCM|AES-256-GCM|aes-128-cbc|aes-256-cbc|aes-128-gcm|aes-256-gcm)
                echo -e "${GREEN}✅ Cipher is supported${NC}"
                ;;
            *)
                echo -e "${RED}❌ ERROR: Unsupported cipher: $CIPHER${NC}"
                echo "   Supported: aes-128-cbc, aes-256-cbc, aes-128-gcm, aes-256-gcm"
                ERRORS=$((ERRORS + 1))
                ;;
        esac
    else
        echo -e "${YELLOW}⚠️  WARNING: Could not detect cipher configuration${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    echo -e "${YELLOW}⚠️  WARNING: config/app.php not found${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

# Check critical environment variables
echo ""
echo "⚙️  Checking critical environment variables..."

check_env_var() {
    local var_name=$1
    local var_value=$(grep "^${var_name}=" .env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    
    if [ -z "$var_value" ]; then
        echo -e "${YELLOW}⚠️  WARNING: $var_name is not set${NC}"
        WARNINGS=$((WARNINGS + 1))
        return 1
    else
        # Mask sensitive values
        if [[ $var_name == *"PASSWORD"* ]] || [[ $var_name == *"KEY"* ]] || [[ $var_name == *"SECRET"* ]]; then
            echo -e "${GREEN}✅ $var_name is set (masked)${NC}"
        else
            echo -e "${GREEN}✅ $var_name=$var_value${NC}"
        fi
        return 0
    fi
}

if [ -f .env ]; then
    check_env_var "APP_ENV"
    check_env_var "APP_DEBUG"
    check_env_var "DB_HOST"
    check_env_var "DB_DATABASE"
    check_env_var "DB_USERNAME"
    check_env_var "DB_PASSWORD"
fi

# Check Docker Compose file
echo ""
echo "🐳 Validating Docker configuration..."
if [ -f docker-compose.prod.yml ]; then
    echo -e "${GREEN}✅ docker-compose.prod.yml exists${NC}"
    
    # Check if env_file is configured
    if grep -q "env_file:" docker-compose.prod.yml; then
        echo -e "${GREEN}✅ env_file is configured${NC}"
    else
        echo -e "${YELLOW}⚠️  WARNING: env_file not found in docker-compose.prod.yml${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
    
    # Check if healthcheck is configured for app
    if grep -q "healthcheck:" docker-compose.prod.yml; then
        echo -e "${GREEN}✅ Healthcheck is configured${NC}"
    else
        echo -e "${YELLOW}⚠️  WARNING: No healthcheck configured for app service${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    echo -e "${YELLOW}⚠️  WARNING: docker-compose.prod.yml not found${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

# Check .dockerignore
echo ""
echo "🛡️  Checking security configuration..."
if [ -f .dockerignore ]; then
    echo -e "${GREEN}✅ .dockerignore exists${NC}"
    
    if grep -q "^\.env$" .dockerignore; then
        echo -e "${GREEN}✅ .env is in .dockerignore${NC}"
    else
        echo -e "${RED}❌ ERROR: .env is NOT in .dockerignore${NC}"
        echo "   Security risk: .env file may be copied into Docker image"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${RED}❌ ERROR: .dockerignore file not found${NC}"
    echo "   Security risk: Secrets may be copied into Docker image"
    ERRORS=$((ERRORS + 1))
fi

# Check .gitignore
if [ -f .gitignore ]; then
    if grep -q "^\.env$" .gitignore; then
        echo -e "${GREEN}✅ .env is in .gitignore${NC}"
    else
        echo -e "${RED}❌ ERROR: .env is NOT in .gitignore${NC}"
        echo "   Security risk: Secrets may be committed to version control"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${YELLOW}⚠️  WARNING: .gitignore file not found${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

# Summary
echo ""
echo "================================================"
echo "📊 Validation Summary"
echo "================================================"

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✅ All validation checks passed!${NC}"
    echo ""
    echo "Your deployment configuration is ready."
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  Validation completed with $WARNINGS warning(s)${NC}"
    echo ""
    echo "Warnings should be reviewed but deployment can proceed."
    exit 0
else
    echo -e "${RED}❌ Validation failed with $ERRORS error(s) and $WARNINGS warning(s)${NC}"
    echo ""
    echo "Please fix the errors above before deploying."
    exit 1
fi
