local Logger = {
    Enabled = true,
    Calls = {},
    File = nil,
    Console = nil
}

local function GetConsole()
    for i, v in pairs(game.CoreGui:GetChildren()) do
        if v.Name == "ParserConsole" then
            for _, child in pairs(v:GetDescendants()) do
                if child:IsA("TextBox") then
                    return child
                end
            end
        end
    end
    return nil
end

local function LogToGame(msg)
    local console = GetConsole()
    if console then
        console.Text = console.Text .. msg .. "\n"
        console.CursorPosition = -1
    end
    warn(msg)
end

local success, file = pcall(function()
    return io.open("ParserLog.txt", "w")
end)

if success then
    Logger.File = file
    LogToGame("[Parser] Log file created")
end

local function Log(data)
    if not Logger.Enabled then return end
    if Logger.File then
        Logger.File:write(data .. "\n")
        Logger.File:flush()
    end
    table.insert(Logger.Calls, data)
    LogToGame(data)
end

LogToGame("[Parser] Script initialized")
LogToGame("[Parser] Hooking functions...")

local oldWait = wait
wait = function(time)
    local info = debug.getinfo(2)
    Log(string.format("[CALL] wait(%.2f) from %s:%d", time or 0, info.source or "?", info.currentline or 0))
    return oldWait(time)
end

local oldSpawn = spawn
spawn = function(func)
    Log(string.format("[CALL] spawn(function) from %s", debug.getinfo(2).source or "?"))
    return oldSpawn(func)
end

if task and task.wait then
    local oldTaskWait = task.wait
    task.wait = function(time)
        Log(string.format("[CALL] task.wait(%.2f)", time or 0))
        return oldTaskWait(time)
    end
end

if getrawmetatable and game then
    local mt = getrawmetatable(game)
    if mt then
        local oldNamecall = mt.__namecall
        local oldIndex = mt.__index
        
        setreadonly(mt, false)
        
        mt.__namecall = newcclosure(function(self, ...)
            local method = getnamecallmethod()
            
            if method == "Connect" then
                Log(string.format("[EVENT] Connect on %s", tostring(self)))
            elseif method == "FireServer" then
                Log(string.format("[REMOTE] FireServer on %s", tostring(self)))
            elseif method == "InvokeServer" then
                Log(string.format("[REMOTE] InvokeServer on %s", tostring(self)))
            end
            
            return oldNamecall(self, ...)
        end)
        
        mt.__index = newcclosure(function(self, key)
            if key == "Name" then
                Log(string.format("[GET] Name property accessed"))
            end
            return oldIndex(self, key)
        end)
        
        setreadonly(mt, true)
        LogToGame("[Parser] Metatable hooks installed")
    end
end

LogToGame("[Parser] All hooks installed successfully!")
LogToGame("[Parser] Watching for game functions...")
LogToGame("[Parser] Commands: /help, /clear, /logs")

local commandHistory = {}
local historyIndex = 0

game:GetService("UserInputService").InputBegan:Connect(function(input, gameProcessed)
    if gameProcessed then return end
    
    if input.KeyCode == Enum.KeyCode.F12 then
        local console = GetConsole()
        if console then
            local parent = console.Parent.Parent
            parent.Visible = not parent.Visible
            LogToGame("[Parser] Console toggled")
        end
    end
end)

LogToGame("[Parser] Press F12 to toggle console")
LogToGame("[Parser] Ready for injection!")