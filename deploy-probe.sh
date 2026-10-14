#!/bin/bash
# Sonda 2: links simbólicos, troca atômica e permissões no ambiente de deploy do cPanel.
# Grava $HOME/ceara-design-probe2.txt. Sem segredos; apaga o que cria.
OUT="$HOME/ceara-design-probe2.txt"
{
    echo "== sonda2 $(date '+%Y-%m-%d %H:%M:%S %z')"
    echo "umask: $(umask)"
    echo "ln: $(type -p ln) | $(ln --version 2>&1 | head -1)"
    echo "mv: $(type -p mv) | alias/func: $(type -t mv)"
    d="$(mktemp -d "$HOME/.probe2-XXXXXX")"; echo "dir: $d"
    mkdir "$d/a" "$d/b"; echo ok > "$d/a/f"; echo ok > "$d/b/f"

    echo "-- 1. ln -s absoluto para pasta no HOME"
    ln -s "$d/a" "$d/cur.tmp"; echo "ln rc=$?"
    ls -la "$d" 2>&1 | sed 's/^/   /'
    mv -T "$d/cur.tmp" "$d/cur"; echo "mv -T rc=$?"
    echo "readlink: $(readlink "$d/cur" 2>&1) | realpath: $(realpath "$d/cur" 2>&1) | conteúdo: $(cat "$d/cur/f" 2>&1)"

    echo "-- 2. troca do link existente para b (ln + mv -T)"
    ln -s "$d/b" "$d/cur.tmp2"; echo "ln rc=$?"
    mv -T "$d/cur.tmp2" "$d/cur"; echo "mv -T rc=$?"
    echo "readlink: $(readlink "$d/cur" 2>&1)"

    echo "-- 3. link relativo e link para arquivo"
    ln -s a "$d/rel"; echo "ln rc=$? readlink=$(readlink "$d/rel" 2>&1) cat=$(cat "$d/rel/f" 2>&1)"
    ln -s "$d/a/f" "$d/flink"; echo "ln rc=$? cat=$(cat "$d/flink" 2>&1)"

    echo "-- 4. link para /tmp (repete a sonda 1)"
    ln -s /tmp "$d/tmplink"; echo "ln rc=$?"; ls -la "$d/tmplink" 2>&1 | sed 's/^/   /'

    echo "-- 5. PHP 8.4: symlink() + rename() sobre link existente"
    /opt/cpanel/ea-php84/root/usr/bin/php -r '
        $d = $argv[1];
        var_dump(symlink("$d/a", "$d/cur.php"));
        var_dump(rename("$d/cur.php", "$d/cur"));
        echo "readlink: ", readlink("$d/cur"), PHP_EOL;
        echo "is_link: ", var_export(is_link("$d/cur"), true), PHP_EOL;
    ' "$d" 2>&1 | sed 's/^/   /'

    echo "-- 6. permissões no clone e com umask 022"
    stat -c '%a %n' "$PWD/deploy-probe.sh" "$PWD/.cpanel.yml" "$PWD" 2>&1 | sed 's/^/   /'
    ( umask 022; mkdir "$d/u"; echo x > "$d/u/x"; stat -c '%a %n' "$d/u" "$d/u/x" | sed 's/^/   /' )
    ( cd "$PWD" && tar -cf - deploy-probe.sh ) | ( umask 022; cd "$d/u" && tar -xf - && stat -c '%a tar-copiado %n' deploy-probe.sh | sed 's/^/   /' )

    echo "-- 7. sistema"
    uname -r
    [ -e /proc/sys/fs/protected_symlinks ] && echo "protected_symlinks=$(cat /proc/sys/fs/protected_symlinks)"
    [ -d /usr/share/cagefs-skeleton ] || [ -e /etc/cagefs ] && echo "CageFS: indícios presentes" || echo "CageFS: sem indícios visíveis"
    rm -rf "$d"
    echo "== fim"
} > "$OUT" 2>&1
chmod 600 "$OUT"
