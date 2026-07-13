#!/bin/bash

set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <major|minor|patch>"
    exit 1
fi

VERSION=$(jq -r '.version' manifest.json)

IFS='.' read -r MAJOR MINOR PATCH <<< "$VERSION"

case "$1" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
    *)
        echo "Invalid argument: $1"
        echo "Usage: $0 <major|minor|patch>"
        exit 1
        ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"

jq --arg version "$NEW_VERSION" '.version = $version' manifest.json > manifest.json.tmp
mv manifest.json.tmp manifest.json

echo "Version bumped to $NEW_VERSION"
echo "Don't forget to update CHANGELOG.md with release notes!"