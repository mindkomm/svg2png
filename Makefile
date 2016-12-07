#!/bin/bash

NAME = svg2png
LATEST_TAG := $(shell git describe master --abbrev=0)
COMMIT := $(shell git rev-parse --verify ${LATEST_TAG})

# Publish commit with newest tag
publish:
	git archive -o ${NAME}_${LATEST_TAG}.zip --prefix=${NAME}/ ${COMMIT}

# Publish current HEAD
publish-current:
	git archive -o ${NAME}-latest.zip --prefix=${NAME}/ HEAD
