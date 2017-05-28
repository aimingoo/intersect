#!/bin/sh

#-----------------------------------------------------
#
#         !!! PLEASE UPDATE THESE HEADERS !!!
#
#-----------------------------------------------------

while read -r line; do
	echo " -H \"${line## }\""
done <<- EndOfHeaders
	Origin: http://aimingoo.github.io
	Referer: http://aimingoo.github.io/untitled/
EndOfHeaders
