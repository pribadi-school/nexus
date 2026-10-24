#!/system/bin/sh
# Counts near-black screenshots while a playlist runs, to prove item changes and loops never go black.
#   adb push android-tv/black-frame-probe.sh /sdcard/probe.sh
#   adb shell sh /sdcard/probe.sh 150        # about 150 samples; play a short mixed playlist meanwhile
# screencap writes raw RGBA; deleting the bytes 0x00 and 0xFF leaves only real picture colour, so a
# black screen leaves almost nothing. Fewer than 100000 bytes counts as near-black.
N=${1:-120}
i=0; low=0
while [ "$i" -lt "$N" ]; do
  c=$(screencap | tr -d '\000\377' | wc -c)
  [ "$c" -lt 100000 ] && low=$((low + 1))
  echo "$i $c"
  i=$((i + 1))
done
echo "DONE samples=$N near-black=$low"
