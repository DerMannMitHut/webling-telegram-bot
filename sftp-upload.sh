#!/bin/bash

# This script uploads the 'webhooks' folder to a remote SFTP server using credentials from environment variables.

# Check required environment variables
if [[ -z "$SFTP_USERNAME" || -z "$SFTP_PASSWORD" || -z "$SFTP_SERVER" || -z "$REMOTE_FOLDER" ]]; then
  echo "Error: WEBHOOK_UUID, SFTP_USERNAME, SFTP_PASSWORD, and SFTP_SERVER environment variables must be set."
  exit 1
fi

# Local folder to upload
LOCAL_FOLDER="webhooks"

# Use lftp for easier scripting of sftp with password
if ! command -v lftp &> /dev/null
then
    echo "lftp command not found. Please install lftp to use this script."
    exit 1
fi

lftp -u "$SFTP_USERNAME","$SFTP_PASSWORD" sftp://"$SFTP_SERVER" <<EOF
mirror -R --verbose --delete --parallel=2 "$LOCAL_FOLDER" "$REMOTE_FOLDER"
chmod 700 "${REMOTE_FOLDER}/wh_config"
chmod 600 "${REMOTE_FOLDER}/wh_config/config.php"
quit
EOF

if [ $? -eq 0 ]; then
  echo "Upload of $LOCAL_FOLDER to $SFTP_SERVER completed successfully."
else
  echo "Upload failed."
fi
