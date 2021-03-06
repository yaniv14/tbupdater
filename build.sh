#!/usr/bin/env bash

FILES=("ajax-upgradetab.php")
FILES+=("alias.php")
FILES+=("cacert.pem")
FILES+=("functions.php")
FILES+=("index.php")
FILES+=("init.php")
FILES+=("logo.gif")
FILES+=("logo.png")
FILES+=("tbupdater.php")
FILES+=("cache/**")
FILES+=("sql/**")
FILES+=("classes/**")
FILES+=("upgrade/**")
FILES+=("views/**")

CWD_BASENAME=${PWD##*/}

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION=${MODULE_VERSION//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}/${CWD_BASENAME}-v${MODULE_VERSION}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
