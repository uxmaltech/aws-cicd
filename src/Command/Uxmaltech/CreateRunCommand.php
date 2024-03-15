<?php

$file_data =<<<'EOT'
#!/bin/bash

# Define tus comandos aquí
COMMANDS=(
"cd /Users/enriquemartinez/Development/PHP/Personalizalo/devtools/src/Command/Uxmaltech/yaml && docker-compose -f kafka.yml up -d"
  "cd /Users/enriquemartinez/Development/PHP/Personalizalo/workshop-backend && APP_ENV=local php artisan serve --port=8001"
  "cd /Users/enriquemartinez/Development/PHP/Personalizalo/workshop-backend && APP_ENV=local php artisan uxmaltech:consume-commands"
  "cd /Users/enriquemartinez/Development/PHP/Personalizalo/workshop-backoffice && APP_ENV=local php artisan serve --port=8000"
  "cd /Users/enriquemartinez/Development/PHP/Personalizalo/workshop-backoffice && npm run dev"
)

WINDOW_ID=$(osascript -e 'tell application "Terminal" to id of front window')

for CMD in "${COMMANDS[@]}"; do
    echo "Ejecutando: $CMD in window $WINDOW_ID"
  # Abre una nueva pestaña en la ventana actual y ejecuta el comando
  osascript -e "
  tell application \"Terminal\"
    activate
    tell application \"System Events\" to keystroke \"t\" using {command down}
    do script \"$CMD\" in front window
  end tell"
done
EOT;
