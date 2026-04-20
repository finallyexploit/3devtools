using System.Text.Json;

namespace loader_goddev;

public sealed class ApiResponse
{
    public string? status { get; set; }
    public string? message { get; set; }
    public string? username { get; set; }
    public JsonElement user_id { get; set; }
    public JsonElement balance { get; set; }
    public bool is_admin { get; set; }
    public int admin_level { get; set; }
    public bool can_ban { get; set; }
    public bool has_license { get; set; }
    public string? license_type { get; set; }
    public string? expires_at { get; set; }
    public string? csrf_token { get; set; }

    public int UserId => ParseInt(user_id);
    public decimal BalanceValue => ParseDecimal(balance);

    private static int ParseInt(JsonElement x)
    {
        if (x.ValueKind == JsonValueKind.Number && x.TryGetInt32(out var n)) return n;
        if (x.ValueKind == JsonValueKind.String && int.TryParse(x.GetString(), out n)) return n;
        return 0;
    }

    private static decimal ParseDecimal(JsonElement x)
    {
        if (x.ValueKind == JsonValueKind.Number && x.TryGetDecimal(out var n)) return n;
        if (x.ValueKind == JsonValueKind.String && decimal.TryParse(x.GetString(), out n)) return n;
        return 0m;
    }
}

public sealed class LicenseInfoResponse
{
    public string? status { get; set; }
    public string? message { get; set; }
    public LicenseInfo? license { get; set; }
}

public sealed class LicenseInfo
{
    public string? license_key { get; set; }
    public string? product_name { get; set; }
    public string? product_type { get; set; }
    public string? expires_at { get; set; }
    public string? status { get; set; }
    public string? activated_at { get; set; }
}
