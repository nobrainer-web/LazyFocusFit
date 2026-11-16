class="$PictureClass lazyload"
style="background-image: url($TinySrc); background-position: $FocusPoint.PercentageX% $FocusPoint.PercentageY%;"
data-bgset="<% loop $PictureSources %><% loop $Sizes %>$URL $Descriptors<% if not $IsLast %>,<% end_if %> <% end_loop %>
    <% if $MinWidth %>
        [(min-width: $MinWidth)]
    <% end_if %>
    <% if not $IsLast %>|<% end_if %>
<% end_loop %>"
<% if $AutoSizes %>
    data-sizes="auto"
<% end_if %>
