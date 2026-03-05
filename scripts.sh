 rm PickupMtaani.zip
 zip -r PickupMtaani.zip . -x "Example/*" ".git/*" "Example" "PickupMtaani.zip" "scripts.sh" ".git" ".idea"
 rm -rf Example/plugins/PickupMtaani
 unzip PickupMtaani.zip -d Example/plugins/PickupMtaani