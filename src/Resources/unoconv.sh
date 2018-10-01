#!/usr/bin/env bash
/usr/bin/unoconv --listener --server=0.0.0.0 --port=2002
cd /unoconvservice
node standalone.js